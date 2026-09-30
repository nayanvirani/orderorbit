<?php

namespace App\Services\Experiences;

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
     * The function offer for an experience config, or null when it gives no saving.
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
                    // Bundle lines are priced by the cart transform; tiers never apply to them.
                    'x' => self::bundleParents($experience),
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

        if (($offer['x'] ?? null) === []) {
            unset($offer['x']);
        }

        return $offer + array_filter(['id' => $experience->handle, 'm' => trim((string) ($c['checkout_label'] ?? '')) ?: null]);
    }

    private static function bundleParents(Experience $experience): array
    {
        return Experience::where('store_id', $experience->store_id)->whereNotNull('bundle_product_id')
            ->pluck('bundle_product_id')
            ->map(fn ($id) => preg_replace('/\D/', '', $id))
            ->values()->all();
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
                && $experience->publishedVersion !== null
                && Registry::has($experience->type)
                && ($experience->ends_at === null || $experience->ends_at->isFuture());
            $offer = $live ? self::offer($experience, $experience->publishedVersion->config) : null;

            if ($offer !== null) {
                $this->upsert($store, $experience, $offer);
            } elseif ($experience->shopify_discount_id) {
                $this->remove($store, $experience);
            }
        }
    }

    private function upsert(Store $store, Experience $experience, array $offer): void
    {
        $shipping = $offer['k'] === 'ship';
        $input = [
            'title' => $offer['m'] ?? $experience->name,
            'startsAt' => ($experience->starts_at ?? $experience->published_at ?? now())->toIso8601String(),
            'endsAt' => $experience->ends_at?->toIso8601String(),
            'discountClasses' => [$shipping ? 'SHIPPING' : 'PRODUCT'],
            // OrderOrbit product offers don't stack with each other; Shopify applies the best one.
            'combinesWith' => ['productDiscounts' => $shipping, 'orderDiscounts' => true, 'shippingDiscounts' => ! $shipping],
        ];
        $value = json_encode(['offers' => [$offer]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
