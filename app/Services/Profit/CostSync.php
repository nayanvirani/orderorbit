<?php

namespace App\Services\Profit;

use App\Models\Profit\ProductCost;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Str;

/**
 * Shopify's "Cost per item" for every variant (read_inventory), kept next to costs set in Growvia.
 * Growvia never writes costs to Shopify unless the merchant turns that option on.
 */
class CostSync
{
    public function __construct(private readonly AdminApi $api) {}

    /** Pulls every variant's title and Shopify cost; returns how many variants were seen. */
    public function sync(Store $store, int $maxPages = 200): int
    {
        $after = null;
        $seen = 0;
        for ($page = 0; $page < $maxPages; $page++) {
            $data = $this->api->graphql($store, 'query ($after: String) { productVariants(first: 250, after: $after) { pageInfo { hasNextPage endCursor }
                nodes { id title displayName product { id title } inventoryItem { unitCost { amount } } } } }', ['after' => $after]);
            foreach ((array) ($data['productVariants']['nodes'] ?? []) as $v) {
                $variant = (int) Str::afterLast((string) $v['id'], '/');
                if (! $variant) {
                    continue;
                }
                $cost = $v['inventoryItem']['unitCost']['amount'] ?? null;
                ProductCost::updateOrCreate(['store_id' => $store->id, 'variant_id' => $variant], [
                    'product_id' => (int) Str::afterLast((string) ($v['product']['id'] ?? ''), '/') ?: null,
                    'title' => mb_substr((string) ($v['displayName'] ?? $v['product']['title'] ?? ''), 0, 255),
                    'shopify_cost' => $cost !== null && $cost !== '' ? round((float) $cost, 2) : null,
                ]);
                $seen++;
            }
            if (! ($data['productVariants']['pageInfo']['hasNextPage'] ?? false)) {
                break;
            }
            $after = $data['productVariants']['pageInfo']['endCursor'];
        }

        return $seen;
    }
}
