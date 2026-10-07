<?php

namespace App\Services\Experiences;

use App\Experiences\BundleSchema;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Real bundles through Shopify's Cart Transform (extensions/orderorbit-bundles).
 *
 * In the cart, the items a shopper adds from a bundle widget merge into one line
 * on the main product's own variant, at the bundle price. No extra product is
 * created. Orders keep the component lines, so Shopify deducts inventory from
 * each product in the bundle.
 */
class BundleSync
{
    public const FUNCTION_HANDLE = 'orderorbit-bundles';

    public function __construct(private readonly AdminApi $api) {}

    public function sync(Store $store): void
    {
        $config = Experience::with('publishedVersion')
            ->where('store_id', $store->id)
            ->where('type', 'bundles')
            ->where('status', 'published')
            ->get()
            // Only bundles the store's plan includes merge at checkout.
            ->filter(fn (Experience $b) => $b->publishedVersion !== null && ($b->ends_at === null || $b->ends_at->isFuture()) && $store->allowsExperience($b->type, $b->publishedVersion->config))
            ->map(fn (Experience $b) => [$b, BundleSchema::normalize($b->publishedVersion->config)[0]])
            ->filter(fn ($pair) => self::merges($pair[1]))
            ->map(fn ($pair) => self::entry(...$pair))
            ->values()->all();

        if ($config === [] && ! $store->cart_transform_id) {
            return;
        }
        $this->writeConfig($store, $config);
    }

    /**
     * Whether any of the bundle's offers merge into one cart line (several products, or mix & match).
     */
    public static function merges(array $bundle): bool
    {
        return in_array($bundle['bundle_type'], ['mix-match', 'byob'], true)
            || collect($bundle['offers'])->contains(fn ($o) => $o['kind'] === 'multi' && $o['products'] !== []);
    }

    /**
     * The cart transform's view of one bundle. Offers are indexed like the bundle's
     * offers (the storefront tags each add with "<bundle>|<offer index>|<group>").
     */
    public static function entry(Experience $bundle, array $config): array
    {
        $ids = fn (array $items) => array_values(array_filter(array_map(
            fn ($p) => preg_match('#(\d+)$#', (string) ($p['id'] ?? ''), $m) ? $m[1] : null,
            $items,
        )));
        $multi = collect($config['offers'])->first(fn ($o) => $o['kind'] === 'multi' && $o['products']);
        $gifts = fn (array $list) => self::giftEntry($config, $list);

        return array_filter([
            'id' => $bundle->handle,
            'title' => $config['settings']['title'] ?: $bundle->name,
            'image' => ($multi['products'][0]['image'] ?? null) ?? ($config['mix']['pool'][0]['image'] ?? null),
            'o' => array_map(fn ($o) => $o['kind'] === 'multi' ? array_filter([
                'p' => $ids($o['products']),
                't' => $o['discount_type'],
                'v' => $o['discount_value'],
                'n' => $o['title'] ?: null,
            ] + $gifts($o['gifts']), fn ($v) => $v !== null) : null, $config['offers']),
            'mix' => self::mixEntry($config),
        ], fn ($v) => $v !== null);
    }

    /**
     * Mix & match and build-your-own-box pricing for the functions (cart transform, and the discount for
     * subscription lines): which products (p, or c: collections, checked live at checkout), the
     * discount steps, and for a box its size limits (min, max), limit per product (pp) and fixed price.
     */
    public static function mixEntry(array $config): ?array
    {
        if (! in_array($config['bundle_type'], ['mix-match', 'byob'], true)) {
            return null;
        }
        $m = $config['mix'];
        $ids = fn (array $items) => array_values(array_filter(array_map(fn ($p) => preg_match('#(\d+)$#', (string) ($p['id'] ?? ''), $x) ? $x[1] : null, $items)));
        $entry = ['p' => $ids($m['pool']), 'tiers' => array_map(fn ($t) => [$t['count'], $t['discount']], $m['tiers'])]
            // The gift unlocks at its item count; by default a box's minimum or a full mix & match.
            + self::giftEntry($config, $config['gifts']['items'] ?? [], ($config['gifts']['unlock'] ?? 0) ?: ($config['bundle_type'] === 'byob' ? $m['min'] : $m['slots']));
        if ($config['bundle_type'] !== 'byob') {
            return $entry;
        }
        if (($m['source'] ?? 'products') === 'collection') {
            $entry = ['c' => array_column($m['collection'], 'id')] + $entry;
            unset($entry['p']);
        }

        return $entry + array_filter([
            'min' => $m['min'], 'max' => $m['slots'], 'pp' => $m['per_product'] ?: null,
            't' => $m['pricing'] === 'fixed' ? 'fixed' : null, 'v' => $m['pricing'] === 'fixed' ? $m['fixed_price'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * The gifts a group may hold for free: gp = the gift product ids, gq = how many units, gm = the
     * number of items that unlocks them (boxes). Empty when gifts are off: gift lines are then paid.
     */
    public static function giftEntry(array $config, array $gifts, int $unlock = 0): array
    {
        $gifts = array_filter($gifts, fn ($g) => ! empty($g['product']));
        if (empty($config['gifts']['enabled']) || ! $gifts) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map(fn ($g) => preg_match('#(\d+)$#', (string) ($g['product'][0]['id'] ?? ''), $m) ? $m[1] : null, $gifts))));

        return ['gp' => $ids, 'gq' => array_sum(array_column($gifts, 'quantity'))] + ($unlock > 0 ? ['gm' => $unlock] : []);
    }

    /** The collections boxes are filled from: checked live by the functions (an input variable). */
    public static function boxCollections(array $entries): array
    {
        return array_values(array_unique(array_merge(...array_map(fn ($e) => $e['mix']['c'] ?? [], $entries ?: [[]]))));
    }

    private function assertEligible(Store $store): void
    {
        $bundles = $this->api->graphql($store, '{ shop { features { bundles { eligibleForBundles ineligibilityReason } } } }')['shop']['features']['bundles'] ?? null;
        if ($bundles !== null && ($bundles['eligibleForBundles'] ?? true) === false) {
            throw new PublishException('Shopify doesn\'t allow bundles on this store yet'.(! empty($bundles['ineligibilityReason']) ? ': '.$bundles['ineligibilityReason'] : '.'), 'unavailable');
        }
    }

    private function writeConfig(Store $store, array $config): void
    {
        $value = json_encode(['bundles' => $config], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // The function's input variables: box collections, so membership is checked live at checkout.
        $vars = json_encode(['boxCollections' => self::boxCollections($config)], JSON_UNESCAPED_SLASHES);
        $fingerprint = 'bundle-config:'.$store->id.':'.md5($value.$vars.$store->cart_transform_id);
        if (Cache::has($fingerprint)) {
            return;
        }

        $metafield = ['namespace' => '$app', 'key' => 'bundles', 'type' => 'json', 'value' => $value];
        $input = ['namespace' => '$app', 'key' => 'input', 'type' => 'json', 'value' => $vars];

        if (! $store->cart_transform_id) {
            $this->assertEligible($store);
            $created = $this->api->graphql($store, <<<'GQL'
                mutation RegisterBundles($handle: String!, $metafields: [MetafieldInput!]) {
                  cartTransformCreate(functionHandle: $handle, blockOnFailure: false, metafields: $metafields) {
                    cartTransform { id }
                    userErrors { field message code }
                  }
                }
                GQL, ['handle' => self::FUNCTION_HANDLE, 'metafields' => [$metafield, $input]]);

            if ($id = $created['cartTransformCreate']['cartTransform']['id'] ?? null) {
                $store->forceFill(['cart_transform_id' => $id])->save();
                Cache::forever('bundle-config:'.$store->id.':'.md5($value.$vars.$id), true);

                return;
            }
            // Already registered (e.g. after a reinstall): reuse it.
            $id = $this->api->graphql($store, '{ cartTransforms(first: 5) { nodes { id } } }')['cartTransforms']['nodes'][0]['id'] ?? null;
            if (! $id) {
                $this->assertNoErrors($created['cartTransformCreate']['userErrors'] ?? [['message' => 'cart transform not created']]);
            }
            $store->forceFill(['cart_transform_id' => $id])->save();
        }

        $result = $this->api->graphql($store, <<<'GQL'
            mutation BundleConfig($metafields: [MetafieldsSetInput!]!) {
              metafieldsSet(metafields: $metafields) { userErrors { field message } }
            }
            GQL, ['metafields' => [['ownerId' => $store->cart_transform_id] + $metafield, ['ownerId' => $store->cart_transform_id] + $input]]);
        $this->assertNoErrors($result['metafieldsSet']['userErrors'] ?? []);
        Cache::forever('bundle-config:'.$store->id.':'.md5($value.$vars.$store->cart_transform_id), true);
    }

    private function assertNoErrors(array $errors): void
    {
        if ($errors !== []) {
            throw new RuntimeException('Bundle setup failed: '.($errors[0]['message'] ?? 'unknown error'));
        }
    }
}
