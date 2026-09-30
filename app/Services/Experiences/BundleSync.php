<?php

namespace App\Services\Experiences;

use App\Experiences\BundleSchema;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Real bundles through Shopify's Cart Transform (extensions/orderorbit-bundles).
 *
 * Each live bundle gets a hidden parent product in the merchant's store (app-owned,
 * untracked inventory, "requires components" so it can't be bought on its own).
 * In the cart, the items a shopper adds from the bundle widget merge into one line
 * on that parent at the bundle price. Orders keep the component lines, so Shopify
 * deducts inventory from each product in the bundle.
 */
class BundleSync
{
    public const FUNCTION_HANDLE = 'orderorbit-bundles';

    public function __construct(private readonly AdminApi $api) {}

    public function sync(Store $store): void
    {
        $bundles = Experience::with('publishedVersion')
            ->where('store_id', $store->id)
            ->where('type', 'bundles')
            ->where(fn ($q) => $q->where('status', 'published')->orWhereNotNull('bundle_product_id'))
            ->get();

        $config = [];
        foreach ($bundles as $bundle) {
            $live = $bundle->status === 'published' && $bundle->publishedVersion !== null
                && ($bundle->ends_at === null || $bundle->ends_at->isFuture());
            $settings = $live ? BundleSchema::normalize($bundle->publishedVersion->config)[0] : null;

            if ($settings && self::merges($settings)) {
                $this->ensureParent($store, $bundle, $settings);
                $config[] = self::entry($bundle, $settings);
            } elseif ($bundle->bundle_product_id) {
                // Not live (or nothing to merge): the parent goes back to draft so it can't reach a cart.
                $this->setParentStatus($store, $bundle, 'DRAFT');
            }
        }

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
        return $bundle['bundle_type'] === 'mix-match'
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

        return array_filter([
            'id' => $bundle->handle,
            'parent' => $bundle->bundle_variant_id,
            'title' => self::title($bundle, $config),
            'image' => self::image($config),
            'o' => array_map(fn ($o) => $o['kind'] === 'multi' ? array_filter([
                'p' => $ids($o['products']),
                't' => $o['discount_type'],
                'v' => $o['discount_value'],
                'n' => $o['title'] ?: null,
            ], fn ($v) => $v !== null) : null, $config['offers']),
            'mix' => $config['bundle_type'] === 'mix-match' ? [
                'p' => $ids($config['mix']['pool']),
                'tiers' => array_map(fn ($t) => [$t['count'], $t['discount']], $config['mix']['tiers']),
            ] : null,
        ], fn ($v) => $v !== null);
    }

    private static function title(Experience $bundle, array $config): string
    {
        return $bundle->name;
    }

    private static function image(array $config): ?string
    {
        $multi = collect($config['offers'])->first(fn ($o) => $o['kind'] === 'multi' && $o['products']);

        return ($multi['products'][0]['image'] ?? null) ?? ($config['mix']['pool'][0]['image'] ?? null);
    }

    private function ensureParent(Store $store, Experience $bundle, array $config): void
    {
        $title = self::title($bundle, $config);
        $multi = collect($config['offers'])->first(fn ($o) => $o['kind'] === 'multi' && $o['products']);
        $items = $multi['products'] ?? array_slice($config['mix']['pool'], 0, $config['mix']['slots']);
        $price = number_format(collect($items)->sum(fn ($p) => (float) ($p['price'] ?? 0) * (int) ($p['quantity'] ?? 1)), 2, '.', '');
        $image = self::image($config);
        // One cached state per bundle: what we last told Shopify (title, price, status).
        $state = 'bundle-state:'.$bundle->id;
        if ($bundle->bundle_product_id && Cache::get($state) === md5($title.$price.$bundle->bundle_variant_id.'ACTIVE')) {
            return;
        }

        if ($bundle->bundle_product_id && $this->updateParent($store, $bundle, $title, $price)) {
            Cache::forever($state, md5($title.$price.$bundle->bundle_variant_id.'ACTIVE'));

            return;
        }

        $this->assertEligible($store);

        $created = $this->api->graphql($store, <<<'GQL'
            mutation CreateBundle($product: ProductCreateInput!, $media: [CreateMediaInput!]) {
              productCreate(product: $product, media: $media) {
                product { id variants(first: 1) { nodes { id } } }
                userErrors { field message }
              }
            }
            GQL, [
            'product' => [
                'title' => $title,
                'status' => 'ACTIVE',
                'productType' => 'Bundle',
                'tags' => ['OrderOrbit bundle'],
                'descriptionHtml' => '<p>Bundle created by OrderOrbit. Items are sold and fulfilled as the individual products.</p>',
                'claimOwnership' => ['bundles' => true],
                // Keep it out of storefront search and sitemaps.
                'metafields' => [['namespace' => 'seo', 'key' => 'hidden', 'type' => 'number_integer', 'value' => '1']],
            ],
            'media' => $image ? [['originalSource' => $image, 'mediaContentType' => 'IMAGE', 'alt' => $title]] : [],
        ]);
        $this->assertNoErrors($created['productCreate']['userErrors'] ?? []);

        $productId = $created['productCreate']['product']['id'] ?? null;
        $variantId = $created['productCreate']['product']['variants']['nodes'][0]['id'] ?? null;
        if (! $productId || ! $variantId) {
            throw new RuntimeException('Shopify did not return the bundle product.');
        }

        $this->updateVariant($store, $productId, $variantId, $price);
        $this->publish($store, $productId);

        $bundle->forceFill(['bundle_product_id' => $productId, 'bundle_variant_id' => $variantId])->saveQuietly();
        Cache::forever($state, md5($title.$price.$variantId.'ACTIVE'));
    }

    /**
     * Returns false when the parent was deleted in Shopify admin, so it's recreated.
     */
    private function updateParent(Store $store, Experience $bundle, string $title, string $price): bool
    {
        $result = $this->api->graphql($store, <<<'GQL'
            mutation UpdateBundle($product: ProductUpdateInput!) {
              productUpdate(product: $product) { product { id } userErrors { field message } }
            }
            GQL, ['product' => ['id' => $bundle->bundle_product_id, 'title' => $title, 'status' => 'ACTIVE']]);

        if (($result['productUpdate']['product'] ?? null) === null) {
            $bundle->forceFill(['bundle_product_id' => null, 'bundle_variant_id' => null])->saveQuietly();

            return false;
        }
        $this->assertNoErrors($result['productUpdate']['userErrors'] ?? []);
        $this->updateVariant($store, $bundle->bundle_product_id, $bundle->bundle_variant_id, $price);

        return true;
    }

    private function updateVariant(Store $store, string $productId, string $variantId, string $price): void
    {
        $result = $this->api->graphql($store, <<<'GQL'
            mutation BundleVariant($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
              productVariantsBulkUpdate(productId: $productId, variants: $variants) { userErrors { field message } }
            }
            GQL, ['productId' => $productId, 'variants' => [[
            'id' => $variantId,
            'price' => $price,
            // Sold only as a bundle; stock comes from the component products.
            'requiresComponents' => true,
            'inventoryItem' => ['tracked' => false],
        ]]]);
        $this->assertNoErrors($result['productVariantsBulkUpdate']['userErrors'] ?? []);
    }

    private function setParentStatus(Store $store, Experience $bundle, string $status): void
    {
        $state = 'bundle-state:'.$bundle->id;
        if (Cache::get($state) === $bundle->bundle_product_id.$status) {
            return;
        }
        $this->api->graphql($store, <<<'GQL'
            mutation BundleStatus($product: ProductUpdateInput!) {
              productUpdate(product: $product) { userErrors { field message } }
            }
            GQL, ['product' => ['id' => $bundle->bundle_product_id, 'status' => $status]]);
        Cache::forever($state, $bundle->bundle_product_id.$status);
    }

    // The parent must be on the Online Store for the bundle line to check out.
    private function publish(Store $store, string $productId): void
    {
        try {
            $publications = $this->api->graphql($store, '{ publications(first: 25) { nodes { id name } } }')['publications']['nodes'] ?? [];
            $online = collect($publications)->first(fn ($p) => stripos($p['name'] ?? '', 'online store') !== false);
            if ($online) {
                $this->api->graphql($store, <<<'GQL'
                    mutation PublishBundle($id: ID!, $input: [PublicationInput!]!) {
                      publishablePublish(id: $id, input: $input) { userErrors { field message } }
                    }
                    GQL, ['id' => $productId, 'input' => [['publicationId' => $online['id']]]]);
            }
        } catch (\Throwable $e) {
            Log::warning('Bundle parent could not be published to the Online Store', ['store' => $store->shop_domain, 'error' => $e->getMessage()]);
        }
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
        $fingerprint = 'bundle-config:'.$store->id.':'.md5($value.$store->cart_transform_id);
        if (Cache::has($fingerprint)) {
            return;
        }

        $metafield = ['namespace' => '$app', 'key' => 'bundles', 'type' => 'json', 'value' => $value];

        if (! $store->cart_transform_id) {
            $created = $this->api->graphql($store, <<<'GQL'
                mutation RegisterBundles($handle: String!, $metafields: [MetafieldInput!]) {
                  cartTransformCreate(functionHandle: $handle, blockOnFailure: false, metafields: $metafields) {
                    cartTransform { id }
                    userErrors { field message code }
                  }
                }
                GQL, ['handle' => self::FUNCTION_HANDLE, 'metafields' => [$metafield]]);

            $id = $created['cartTransformCreate']['cartTransform']['id'] ?? null;
            if (! $id) {
                // Already registered (e.g. after a reinstall): reuse it.
                $id = $this->api->graphql($store, '{ cartTransforms(first: 5) { nodes { id } } }')['cartTransforms']['nodes'][0]['id'] ?? null;
                if (! $id) {
                    $this->assertNoErrors($created['cartTransformCreate']['userErrors'] ?? [['message' => 'cart transform not created']]);
                }
            } else {
                $store->forceFill(['cart_transform_id' => $id])->save();
                Cache::forever('bundle-config:'.$store->id.':'.md5($value.$id), true);

                return;
            }
            $store->forceFill(['cart_transform_id' => $id])->save();
        }

        $result = $this->api->graphql($store, <<<'GQL'
            mutation BundleConfig($metafields: [MetafieldsSetInput!]!) {
              metafieldsSet(metafields: $metafields) { userErrors { field message } }
            }
            GQL, ['metafields' => [['ownerId' => $store->cart_transform_id] + $metafield]]);
        $this->assertNoErrors($result['metafieldsSet']['userErrors'] ?? []);
        Cache::forever('bundle-config:'.$store->id.':'.md5($value.$store->cart_transform_id), true);
    }

    private function assertNoErrors(array $errors): void
    {
        if ($errors !== []) {
            throw new RuntimeException('Bundle setup failed: '.($errors[0]['message'] ?? 'unknown error'));
        }
    }
}
