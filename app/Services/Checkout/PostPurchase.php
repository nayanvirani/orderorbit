<?php

namespace App\Services\Checkout;

use App\Experiences\Registry;
use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use App\Services\Shopify\ShopDomain;
use App\Support\Jwt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The post-purchase funnel behind the orderorbit-post-purchase extension. Shopify signs a token
 * for each purchase; with it the extension asks which offer to show, and asks us to sign the
 * order change when the shopper accepts. Offers and prices are always worked out here, never
 * taken from the browser, so a changed request can't alter what is charged.
 */
class PostPurchase
{
    public function __construct(private readonly AdminApi $api) {}

    /**
     * Verifies Shopify's token for this purchase. Returns the store and the purchase facts, taken
     * from the signed token when it carries them (products, total, reference) and otherwise from
     * what the extension sent.
     *
     * @return array{0: Store, 1: array{products: list<string>, total: float, reference_id: string}}
     */
    public function context(string $token, string $shop, array $sent): array
    {
        $claims = null;
        foreach (\App\Services\Shopify\Secrets::verifying() as $secret) {
            try {
                $claims = Jwt::decode($token, $secret);
                break;
            } catch (RuntimeException $e) {
                $failure = $e;
            }
        }
        if ($claims === null) {
            throw $failure ?? new RuntimeException('Invalid token signature.');
        }
        // The shop comes from the signed token only; the shop the extension sends must be the same one.
        $domain = self::tokenShop($claims);
        if ($domain === null || $domain !== strtolower($shop)) {
            throw new RuntimeException('The token belongs to another shop.');
        }
        $store = Store::where('shop_domain', $domain)->whereNull('uninstalled_at')->firstOrFail();

        $purchase = $claims['input_data']['initialPurchase'] ?? null;
        $facts = is_array($purchase) ? [
            'products' => array_values(array_filter(array_map(fn ($l) => self::id((string) ($l['product']['id'] ?? '')), $purchase['lineItems'] ?? []))),
            'total' => (float) ($purchase['totalPriceSet']['shopMoney']['amount'] ?? 0),
            'reference_id' => (string) ($purchase['referenceId'] ?? ''),
        ] : $sent;

        return [$store, $facts];
    }

    /** The shop a purchase token was signed for: its `dest` claim, or the shop in its input data. */
    private static function tokenShop(array $claims): ?string
    {
        $dest = strtolower(trim((string) ($claims['dest'] ?? '')));
        $domain = $dest !== '' ? (parse_url(str_contains($dest, '://') ? $dest : 'https://'.$dest, PHP_URL_HOST) ?: '') : strtolower((string) ($claims['input_data']['shop']['domain'] ?? ''));

        return ShopDomain::isValid($domain) ? $domain : null;
    }

    /**
     * The funnel for this purchase: the highest-priority live post-purchase experience whose
     * trigger matches, with its first offer and optional second offer. Null when none applies.
     *
     * @param  list<string>  $productIds  products in the order (numeric ids)
     */
    /** The A/B test version the last funnel() chose: [test handle, version key, offer handle], or null (G39). */
    public ?array $lastTest = null;

    public function funnel(Store $store, array $productIds, float $total, string $reference = ''): ?array
    {
        $this->lastTest = null;
        if (! $store->planIncludes('post_purchase') || \App\Support\Features::killed('post_purchase')) {
            return null;
        }

        $experience = Experience::with('publishedVersion')
            ->where('store_id', $store->id)->where('type', 'post-purchase')->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get()
            ->filter(fn (Experience $e) => $e->publishedVersion && $this->matches($e->publishedVersion->config['content'] ?? [], $productIds, $total))
            ->sortByDesc(fn (Experience $e) => (int) ($e->publishedVersion->config['behavior']['priority'] ?? 50))
            ->first();

        if (! $experience) {
            return null;
        }

        $c = $experience->publishedVersion->config['content'];
        // A running A/B test (G39): the order's version, the same on every retry of this order.
        if ($picked = $this->version($store, $experience, $reference)) {
            $this->lastTest = [$picked[0], $picked[1]->key, $experience->handle];
            if ($picked[1]->hidden) {
                return null;
            }
            $c = array_merge($c, $picked[1]->content ?? []);
        }
        $offers = array_values(array_filter([
            $this->offer($store, $c['offer_product'][0] ?? null, (int) ($c['discount_percent'] ?? 0), (string) ($c['headline'] ?? ''), $productIds),
            ! empty($c['downsell']) ? $this->offer($store, $c['downsell_product'][0] ?? null, (int) ($c['downsell_discount'] ?? 0), (string) ($c['downsell_headline'] ?? ''), $productIds) : null,
        ]));
        if (! $offers) {
            return null;
        }

        return [
            'experience_id' => $experience->handle,
            'style' => Registry::template('post-purchase', $experience->publishedVersion->template_key)['style'] ?? 'classic',
            'message' => $c['message'] ?? '',
            'accept_text' => $c['accept_text'] ?? 'Pay now',
            'decline_text' => $c['decline_text'] ?? 'Decline this offer',
            // Texts and visibility.
            'hide' => array_values((array) ($c['hide'] ?? [])),
            'words' => (object) ($c['words'] ?? []),
            'offers' => array_map(fn ($o, $i) => ['step' => $i] + $o, $offers, array_keys($offers)),
        ];
    }

    /**
     * The version of a running test on this offer for an order: a stable share of the order reference,
     * so a retry sees the same one. Equal shares without Traffic allocation & guardrails.
     *
     * @return array{0: string, 1: \App\Models\Experiments\ExperimentVariant}|null
     */
    private function version(Store $store, Experience $experience, string $reference): ?array
    {
        if ($reference === '' || ! $store->planIncludes('ab_testing')) {
            return null;
        }
        $test = \App\Models\Experiments\Experiment::with('variants')->where('experience_id', $experience->id)->where('status', 'running')->first();
        if (! $test || $test->variants->count() < 2) {
            return null;
        }
        $even = ! $store->planIncludes('ab_traffic_guardrails');
        $count = $test->variants->count();
        $bucket = crc32($test->handle.':'.$reference) % 100;
        $sum = 0;
        foreach ($test->variants->values() as $i => $v) {
            $sum += $even ? intdiv(100, $count) + ($i < 100 % $count ? 1 : 0) : $v->allocation;
            if ($bucket < $sum) {
                return [$test->handle, $v];
            }
        }

        return [$test->handle, $test->variants->first()];
    }

    /**
     * Signs the change that adds offer $step of the funnel to the order.
     */
    public function sign(Store $store, string $referenceId, string $experienceId, int $step, array $productIds, float $total): string
    {
        $funnel = $this->funnel($store, $productIds, $total, $referenceId);
        $offer = $funnel && $funnel['experience_id'] === $experienceId ? ($funnel['offers'][$step] ?? null) : null;
        if (! $offer || $referenceId === '') {
            throw new RuntimeException('This offer is no longer available.');
        }

        $change = ['type' => 'add_variant', 'variantId' => (int) $offer['variant_id'], 'quantity' => 1];
        if ($offer['discount_percent'] > 0) {
            $change['discount'] = ['value' => $offer['discount_percent'], 'valueType' => 'percentage', 'title' => $offer['discount_percent'].'% off'];
        }

        $this->record($store, $experienceId, 'accept', round($offer['price'] * (1 - $offer['discount_percent'] / 100), 2), $referenceId, $this->lastTest);

        return Jwt::encode([
            'iss' => config('shopify.api_key'),
            'jti' => (string) Str::uuid(),
            'iat' => (int) round(microtime(true) * 1000), // milliseconds, as Shopify's examples sign it
            'sub' => $referenceId,
            'changes' => [$change],
        ], (string) config('shopify.api_secret'));
    }

    /** An offer event; with a test, the order counts as the visitor "pp:<reference>" in that version. */
    public function record(Store $store, string $experienceId, string $event, float $value = 0, ?string $reference = null, ?array $test = null): void
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $experienceId)) {
            return;
        }
        $who = $test && $reference ? ['visitor_id' => 'pp:'.mb_substr($reference, 0, 60), 'experiment_handle' => $test[0], 'variant' => $test[1], 'experience_type' => 'post-purchase'] : [];
        AnalyticsEvent::create(['store_id' => $store->id, 'experience_handle' => $experienceId, 'event' => $event, 'value' => $value, 'currency' => $store->currency, 'occurred_at' => now()] + $who);
        if ($event === 'accept' && $value > 0) {
            // Revenue the funnel added to the order, for the offer's numbers.
            AnalyticsEvent::create(['store_id' => $store->id, 'experience_handle' => $experienceId, 'event' => 'attributed', 'value' => $value, 'currency' => $store->currency, 'occurred_at' => now()]);
        }
    }

    private function matches(array $c, array $productIds, float $total): bool
    {
        return match ($c['trigger'] ?? 'any') {
            'products' => (bool) array_intersect(array_map(fn ($p) => self::id((string) ($p['id'] ?? '')), $c['trigger_products'] ?? []), $productIds),
            'min_total' => $total >= (float) ($c['min_total'] ?? 0),
            default => true,
        };
    }

    /**
     * One offer: the product's chosen (or first available) variant, live from Shopify. Skipped if
     * it's unavailable or already in the order.
     */
    private function offer(Store $store, ?array $product, int $discount, string $headline, array $productIds): ?array
    {
        $productId = self::id((string) ($product['id'] ?? ''));
        if ($productId === '' || in_array($productId, $productIds, true)) {
            return null;
        }
        $live = Cache::remember("post-purchase:product:{$store->id}:{$productId}", 300, fn () => $this->api->graphql($store, <<<'GQL'
            query ($id: ID!) {
              product(id: $id) {
                title
                featuredMedia { preview { image { url(transform: {maxWidth: 800}) } } }
                variants(first: 50) { nodes { id title price availableForSale } }
              }
            }
            GQL, ['id' => 'gid://shopify/Product/'.$productId])['product'] ?? null);
        if (! $live) {
            return null;
        }

        $mapped = array_map(fn ($v) => self::id((string) ($v['id'] ?? '')), $product['variants'] ?? []);
        $variant = collect($live['variants']['nodes'] ?? [])
            ->filter(fn ($v) => ! empty($v['availableForSale']) && (! $mapped || in_array(self::id($v['id']), $mapped, true)))
            ->first();
        if (! $variant) {
            return null;
        }

        return [
            'variant_id' => self::id($variant['id']),
            'product_title' => $live['title'],
            'variant_title' => $variant['title'] === 'Default Title' ? null : $variant['title'],
            'image' => $live['featuredMedia']['preview']['image']['url'] ?? ($product['image'] ?? null),
            'price' => (float) $variant['price'],
            'discount_percent' => max(0, min(90, $discount)),
            'headline' => $headline,
        ];
    }

    private static function id(string $gid): string
    {
        return preg_match('/(\d+)$/', $gid, $m) ? $m[1] : '';
    }
}
