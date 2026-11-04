<?php

namespace App\Services\Profit;

use App\Models\Profit\ProductCost;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Str;

/**
 * Every variant as Shopify has it: "Cost per item" (read_inventory), price (and when it changed),
 * stock in total and per location (read_locations), and the product data Protect checks (image,
 * description, SKU, barcode, weight, status). Costs set in Growvia are kept next to Shopify's.
 * Growvia never writes to Shopify unless the merchant turns that option on.
 */
class CostSync
{
    public function __construct(private readonly AdminApi $api) {}

    /** Pulls every variant; returns how many were seen. */
    public function sync(Store $store, int $maxPages = 400): int
    {
        // Stock per location makes each variant cost more to ask for, so pages are smaller then.
        $first = $store->hasScope('read_locations') ? 50 : 250;
        $after = null;
        $seen = 0;
        $started = now();
        for ($page = 0; $page < $maxPages; $page++) {
            $data = $this->api->graphql($store, "query (\$after: String) { productVariants(first: {$first}, after: \$after) { pageInfo { hasNextPage endCursor }
                nodes { ".$this->fields($store).' } } }', ['after' => $after]);
            foreach ((array) ($data['productVariants']['nodes'] ?? []) as $v) {
                if ($this->save($store, $v)) {
                    $seen++;
                }
            }
            if (! ($data['productVariants']['pageInfo']['hasNextPage'] ?? false)) {
                // Variants that are gone from Shopify stop counting as missing data.
                ProductCost::where('store_id', $store->id)->where(fn ($q) => $q->whereNull('seen_at')->orWhere('seen_at', '<', $started))->update(['product_status' => 'DELETED']);
                break;
            }
            $after = $data['productVariants']['pageInfo']['endCursor'];
        }

        return $seen;
    }

    /** The variant fields read from Shopify (stock per location only with read_locations). */
    private function fields(Store $store): string
    {
        $levels = $store->hasScope('read_locations') ? 'inventoryLevels(first: 10) { nodes { location { id name } quantities(names: ["available"]) { quantity } } }' : '';

        return "id displayName price sku barcode inventoryQuantity image { id }
            product { id title status isGiftCard description(truncateAt: 5) featuredMedia { id } }
            inventoryItem { tracked unitCost { amount } measurement { weight { value } } {$levels} }";
    }

    /**
     * One product again (from a products/* or inventory webhook): its variants as Shopify has them
     * now. Variants no longer on the product are marked deleted. Returns whether anything was read.
     */
    public function syncProduct(Store $store, int $productId): bool
    {
        $data = $this->api->graphql($store, 'query ($id: ID!) { product(id: $id) { variants(first: 100) { nodes { '.$this->fields($store).' } } } }', ['id' => 'gid://shopify/Product/'.$productId]);
        $nodes = (array) ($data['product']['variants']['nodes'] ?? []);
        $seen = [];
        foreach ($nodes as $v) {
            if ($this->save($store, $v)) {
                $seen[] = (int) Str::afterLast((string) $v['id'], '/');
            }
        }
        ProductCost::where('store_id', $store->id)->where('product_id', $productId)->whereNotIn('variant_id', $seen ?: [0])->update(['product_status' => 'DELETED']);

        return $nodes !== [];
    }

    /** The product an inventory item belongs to (inventory webhooks only send the item id). */
    public function productForInventoryItem(Store $store, int $itemId): ?int
    {
        $data = $this->api->graphql($store, 'query ($id: ID!) { inventoryItem(id: $id) { variant { product { id } } } }', ['id' => 'gid://shopify/InventoryItem/'.$itemId]);
        $product = $data['inventoryItem']['variant']['product']['id'] ?? null;

        return $product ? (int) Str::afterLast((string) $product, '/') : null;
    }

    private function save(Store $store, array $v): bool
    {
        $variant = (int) Str::afterLast((string) ($v['id'] ?? ''), '/');
        if (! $variant) {
            return false;
        }
        $item = (array) ($v['inventoryItem'] ?? []);
        $cost = $item['unitCost']['amount'] ?? null;
        $price = isset($v['price']) && is_numeric($v['price']) ? round((float) $v['price'], 2) : null;
        $existing = ProductCost::where('store_id', $store->id)->where('variant_id', $variant)->first();
        $levels = [];
        foreach ((array) ($item['inventoryLevels']['nodes'] ?? []) as $level) {
            $levels[(string) ($level['location']['id'] ?? '')] = [(string) ($level['location']['name'] ?? ''), (int) ($level['quantities'][0]['quantity'] ?? 0)];
        }

        $values = [
            'product_id' => (int) Str::afterLast((string) ($v['product']['id'] ?? ''), '/') ?: null,
            'title' => mb_substr((string) ($v['displayName'] ?? $v['product']['title'] ?? ''), 0, 255),
            'shopify_cost' => $cost !== null && $cost !== '' ? round((float) $cost, 2) : null,
            'price' => $price,
            'sku' => ($v['sku'] ?? '') !== '' ? mb_substr((string) $v['sku'], 0, 120) : null,
            'barcode' => ($v['barcode'] ?? '') !== '' ? mb_substr((string) $v['barcode'], 0, 120) : null,
            'weight' => isset($item['measurement']['weight']['value']) && (float) $item['measurement']['weight']['value'] > 0 ? (float) $item['measurement']['weight']['value'] : null,
            'available' => isset($v['inventoryQuantity']) ? (int) $v['inventoryQuantity'] : null,
            'locations' => $levels ?: null,
            'tracked' => (bool) ($item['tracked'] ?? false),
            'has_image' => ! empty($v['image']['id']) || ! empty($v['product']['featuredMedia']['id']),
            'has_description' => trim((string) ($v['product']['description'] ?? '')) !== '',
            // Gift cards have no real stock or product data: kept out of Protect.
            'product_status' => ! empty($v['product']['isGiftCard']) ? 'GIFT_CARD' : ($v['product']['status'] ?? null),
            'seen_at' => now(),
        ];
        // A price change is kept for the "price mistake" alert.
        if ($existing && $existing->price !== null && $price !== null && abs($existing->price - $price) >= 0.01) {
            $values['previous_price'] = $existing->price;
            $values['price_changed_at'] = now();
        }
        ProductCost::updateOrCreate(['store_id' => $store->id, 'variant_id' => $variant], $values);

        return true;
    }
}
