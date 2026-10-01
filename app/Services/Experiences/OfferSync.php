<?php

namespace App\Services\Experiences;

use App\Experiences\BundleSchema;
use App\Experiences\GiftSchema;
use App\Experiences\Registry;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Real savings at checkout. Every live experience that promises a saving
 * (quantity break, BOGO, upsell incentive, free gift, free shipping)
 * gets its own Shopify automatic discount powered by the OrderOrbit discount
 * function (extensions/orderorbit-discounts). The discount's "offers" metafield
 * tells the function what to apply; Shopify handles the start and end dates,
 * and merchants see each offer in Shopify's Discounts list.
 */
class OfferSync
{
    public const FUNCTION_HANDLE = 'orderorbit-discounts';

    public function __construct(private readonly AdminApi $api) {}

    /**
     * Every discount-function offer for an experience (one Shopify discount carries them all).
     */
    public static function offersFor(Experience $experience, array $config): array
    {
        if ($experience->type === 'progressive-gifts') {
            [$gifts] = GiftSchema::normalize($config);
            $milestones = array_map(fn ($m) => array_filter([
                't' => $m['threshold'],
                'r' => $m['reward'],
                'v' => in_array($m['reward'], ['percent', 'amount'], true) ? $m['value'] : null,
                'q' => in_array($m['reward'], ['gift', 'choice'], true) ? $m['quantity'] : null,
            ], fn ($v) => $v !== null), $gifts['milestones']);

            return $milestones ? [['k' => 'pg', 'id' => $experience->handle, 'by' => $gifts['settings']['unlock'], 'm' => $milestones, 'n' => $gifts['settings']['title'] ?: 'Reward unlocked']] : [];
        }
        if ($experience->type !== 'bundles') {
            return array_values(array_filter([self::offer($experience, $config)]));
        }

        [$bundle] = BundleSchema::normalize($config);
        $offers = [];
        // One-product offers (quantity breaks, variant offers) and their gifts. Offers of several
        // products are merged and priced by the cart transform, so they're null here.
        $perOffer = array_map(fn ($o) => $o['kind'] === 'multi' ? null : array_filter([
            'q' => $o['quantity'],
            't' => $o['discount_type'],
            'v' => $o['discount_value'],
            'g' => array_sum(array_map(fn ($g) => $g['product'] ? $g['quantity'] : 0, $o['gifts'])),
        ], fn ($v) => $v !== 0 && $v !== 0.0), $bundle['offers']);
        if (array_filter($perOffer, fn ($o) => $o !== null && (($o['t'] ?? 'none') !== 'none' || ($o['g'] ?? 0) > 0))) {
            $offers[] = ['k' => 'bq', 'id' => $experience->handle, 'o' => $perOffer, 'm' => $bundle['settings']['title'] ?: 'Bundle discount'];
        }
        if ($bundle['upsells']['enabled'] && $bundle['upsells']['discount_percent'] > 0 && $bundle['upsells']['products']) {
            $offers[] = ['k' => 'upsell', 'id' => $experience->handle.':u', 'v' => $bundle['upsells']['discount_percent'], 'm' => $bundle['upsells']['title'] ?: 'Add-on offer'];
        }

        return $offers;
    }

    /**
     * The function offer for a (non-bundle) experience config, or null when it gives no saving.
     */
    public static function offer(Experience $experience, array $config): ?array
    {
        $c = $config['content'] ?? [];
        $ids = fn ($list) => array_values(array_filter(array_map(
            fn ($p) => preg_match('#(\d+)$#', (string) ($p['id'] ?? ''), $m) ? $m[1] : null,
            is_array($list) ? $list : [],
        )));

        $offer = match ($experience->type) {
            'quantity-breaks' => ($tiers = collect($c['tiers'] ?? [])
                ->filter(fn ($t) => (float) ($t['discount'] ?? 0) > 0 && (int) ($t['quantity'] ?? 0) > 0)
                ->map(fn ($t) => [(int) $t['quantity'], (float) $t['discount']])
                ->values()->all()) ? [
                    'k' => 'tiers',
                    'p' => $ids($c['products'] ?? []) ?: $ids($config['targeting']['products'] ?? []),
                    'tiers' => $tiers,
                ] : null,
            'bogo' => [
                'k' => 'bogo',
                'p' => $ids($c['buy_products'] ?? []),
                'g' => $ids($c['get_products'] ?? []),
                'bq' => max(1, (int) ($c['buy_quantity'] ?? 1)),
                'gq' => max(1, (int) ($c['get_quantity'] ?? 1)),
                'v' => (float) ($c['get_discount'] ?? 100),
                'once' => ! ($c['repeat'] ?? true),
            ],
            'product-upsells', 'cart-upsells' => (float) ($c['discount_percent'] ?? 0) > 0
                ? ['k' => 'upsell', 'v' => (float) $c['discount_percent']] : null,
            'free-gifts' => ! empty($c['gift_products']) && ($amounts = collect($c['thresholds'] ?? [])->pluck('amount')->filter(fn ($a) => $a !== null && $a !== '')->map(fn ($a) => (float) $a)->sort()->values()->all())
                ? ['k' => 'gift', 'th' => $amounts] : null,
            'shipping-bar' => ! empty($c['free_shipping']) && ($first = collect($c['thresholds'] ?? [])->pluck('amount')->filter(fn ($a) => $a !== null && $a !== '')->min()) !== null
                ? ['k' => 'ship', 'min' => (float) $first] : null,
            default => null,
        };

        if ($offer === null || (isset($offer['p']) && $offer['p'] === [] && $offer['k'] !== 'tiers')) {
            return null;
        }

        return $offer + array_filter(['id' => $experience->handle, 'm' => trim((string) ($c['checkout_label'] ?? '')) ?: null]);
    }

    /**
     * Creates, updates or removes the store's OrderOrbit discounts to match its live experiences.
     */
    public function sync(Store $store): void
    {
        $experiences = Experience::with('publishedVersion')
            ->where('store_id', $store->id)
            ->where(fn ($q) => $q->whereNotNull('shopify_discount_id')->orWhere('status', 'published'))
            ->get();

        foreach ($experiences as $experience) {
            $live = $experience->status === 'published'
                && ! $store->offersSuspended()
                && $experience->publishedVersion !== null
                && Registry::has($experience->type)
                && ($experience->ends_at === null || $experience->ends_at->isFuture());
            $offers = $live ? self::offersFor($experience, $experience->publishedVersion->config) : [];

            if ($offers !== []) {
                $this->upsert($store, $experience, $offers);
            } elseif ($experience->shopify_discount_id) {
                $this->remove($store, $experience);
            }
        }
    }

    private function upsert(Store $store, Experience $experience, array $offers): void
    {
        $shipping = $offers[0]['k'] === 'ship';
        $classes = [$shipping ? 'SHIPPING' : 'PRODUCT'];
        if ($offers[0]['k'] === 'pg') {
            $rewards = array_column($offers[0]['m'], 'r');
            $classes = array_values(array_filter([
                array_intersect($rewards, ['gift', 'choice']) ? 'PRODUCT' : null,
                array_intersect($rewards, ['percent', 'amount']) ? 'ORDER' : null,
                in_array('shipping', $rewards, true) ? 'SHIPPING' : null,
            ]));
        }
        $input = [
            'title' => $offers[0]['m'] ?? $offers[0]['n'] ?? $experience->name,
            'startsAt' => ($experience->starts_at ?? $experience->published_at ?? now())->toIso8601String(),
            'endsAt' => $experience->ends_at?->toIso8601String(),
            'discountClasses' => $classes,
            // OrderOrbit product offers don't stack with each other; Shopify applies the best one.
            'combinesWith' => ['productDiscounts' => $shipping || $offers[0]['k'] === 'pg', 'orderDiscounts' => true, 'shippingDiscounts' => ! $shipping],
        ];
        $value = json_encode(['offers' => $offers], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $fingerprint = 'offer:'.$experience->id.':'.md5($value.json_encode($input).$experience->shopify_discount_id);
        if (Cache::has($fingerprint)) {
            return;
        }

        if ($experience->shopify_discount_id && $this->update($store, $experience->shopify_discount_id, $input, $value)) {
            Cache::forever($fingerprint, true);

            return;
        }

        $result = $this->api->graphql($store, <<<'GQL'
            mutation Create($discount: DiscountAutomaticAppInput!) {
              discountAutomaticAppCreate(automaticAppDiscount: $discount) {
                automaticAppDiscount { discountId }
                userErrors { field message }
              }
            }
            GQL, ['discount' => $input + [
            'functionHandle' => self::FUNCTION_HANDLE,
            'metafields' => [['namespace' => '$app', 'key' => 'offers', 'type' => 'json', 'value' => $value]],
        ]]);

        $this->assertNoErrors($result['discountAutomaticAppCreate']['userErrors'] ?? []);
        $id = $result['discountAutomaticAppCreate']['automaticAppDiscount']['discountId'] ?? null;
        if (! $id) {
            throw new RuntimeException('Shopify did not return the new discount.');
        }

        $experience->forceFill(['shopify_discount_id' => $id])->saveQuietly();
        Cache::forever('offer:'.$experience->id.':'.md5($value.json_encode($input).$id), true);
    }

    /**
     * Returns false when the discount no longer exists (deleted in Shopify admin), so it's recreated.
     */
    private function update(Store $store, string $id, array $input, string $value): bool
    {
        $result = $this->api->graphql($store, <<<'GQL'
            mutation Update($id: ID!, $discount: DiscountAutomaticAppInput!, $metafields: [MetafieldsSetInput!]!) {
              discountAutomaticAppUpdate(id: $id, automaticAppDiscount: $discount) {
                automaticAppDiscount { discountId }
                userErrors { field message }
              }
              metafieldsSet(metafields: $metafields) { userErrors { field message } }
            }
            GQL, ['id' => $id, 'discount' => $input, 'metafields' => [[
            'ownerId' => $id, 'namespace' => '$app', 'key' => 'offers', 'type' => 'json', 'value' => $value,
        ]]]);

        $errors = $result['discountAutomaticAppUpdate']['userErrors'] ?? [];
        if ($errors !== [] && collect($errors)->contains(fn ($e) => stripos($e['message'] ?? '', 'not exist') !== false || stripos($e['message'] ?? '', 'not found') !== false)) {
            return false;
        }

        $this->assertNoErrors([...$errors, ...($result['metafieldsSet']['userErrors'] ?? [])]);

        return true;
    }

    private function remove(Store $store, Experience $experience): void
    {
        $result = $this->api->graphql($store, <<<'GQL'
            mutation Remove($id: ID!) {
              discountAutomaticDelete(id: $id) { userErrors { field message } }
            }
            GQL, ['id' => $experience->shopify_discount_id]);

        // Already deleted in Shopify admin is fine.
        $errors = collect($result['discountAutomaticDelete']['userErrors'] ?? [])
            ->reject(fn ($e) => stripos($e['message'] ?? '', 'not exist') !== false || stripos($e['message'] ?? '', 'not found') !== false)
            ->all();
        $this->assertNoErrors($errors);

        $experience->forceFill(['shopify_discount_id' => null])->saveQuietly();
    }

    private function assertNoErrors(array $errors): void
    {
        if ($errors !== []) {
            throw new RuntimeException('Discount setup failed: '.($errors[0]['message'] ?? 'unknown error'));
        }
    }
}
