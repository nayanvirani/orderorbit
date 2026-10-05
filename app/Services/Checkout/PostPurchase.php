<?php

namespace App\Services\Checkout;

use App\Experiences\Registry;
use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
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
        $claims = Jwt::decode($token, (string) config('shopify.api_secret'));
        $domain = strtolower($shop);
        if (isset($claims['dest']) && ! str_contains(strtolower((string) $claims['dest']), $domain)) {
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

    /**
     * The funnel for this purchase: the highest-priority live post-purchase experience whose
     * trigger matches, with its first offer and optional second offer. Null when none applies.
     *
     * @param  list<string>  $productIds  products in the order (numeric ids)
     */
    public function funnel(Store $store, array $productIds, float $total): ?array
    {
        if (! $store->planIncludes('post_purchase')) {
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
            'offers' => array_map(fn ($o, $i) => ['step' => $i] + $o, $offers, array_keys($offers)),
        ];
    }

    /**
     * Signs the change that adds offer $step of the funnel to the order.
     */
    public function sign(Store $store, string $referenceId, string $experienceId, int $step, array $productIds, float $total): string
    {
        $funnel = $this->funnel($store, $productIds, $total);
        $offer = $funnel && $funnel['experience_id'] === $experienceId ? ($funnel['offers'][$step] ?? null) : null;
        if (! $offer || $referenceId === '') {
            throw new RuntimeException('This offer is no longer available.');
        }

        $change = ['type' => 'add_variant', 'variantId' => (int) $offer['variant_id'], 'quantity' => 1];
        if ($offer['discount_percent'] > 0) {
            $change['discount'] = ['value' => $offer['discount_percent'], 'valueType' => 'percentage', 'title' => $offer['discount_percent'].'% off'];
        }

        $this->record($store, $experienceId, 'accept', round($offer['price'] * (1 - $offer['discount_percent'] / 100), 2));

        return Jwt::encode([
            'iss' => config('shopify.api_key'),
            'jti' => (string) Str::uuid(),
            'iat' => (int) round(microtime(true) * 1000), // milliseconds, as Shopify's examples sign it
            'sub' => $referenceId,
            'changes' => [$change],
        ], (string) config('shopify.api_secret'));
    }

    public function record(Store $store, string $experienceId, string $event, float $value = 0): void
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $experienceId)) {
            return;
        }
        AnalyticsEvent::create(['store_id' => $store->id, 'experience_handle' => $experienceId, 'event' => $event, 'value' => $value, 'currency' => $store->currency, 'occurred_at' => now()]);
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
