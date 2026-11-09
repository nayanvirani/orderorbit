<?php

namespace App\Automation;

use App\Models\Profit\ProfitOrderLine;
use App\Models\Store;
use App\Support\Features;
use Illuminate\Support\Facades\Cache;

/**
 * Predictive reorder timing (G21): each product's usual gap between a customer's purchases of it,
 * from the store's synced orders (customer ids only). A wait set to "the usual reorder time"
 * waits that long; with too little history it falls back to the fixed wait.
 */
class ReorderTiming
{
    /** Repeat purchases a product needs before its gap is trusted. */
    public const MIN_REPEATS = 5;

    public static function available(Store $store): bool
    {
        return Features::enabled('reorder_timing', $store) && $store->planIncludes('reorder_timing');
    }

    /** Days until the soonest usual reorder among the products, or null without enough history. */
    public function days(Store $store, array $productIds): ?int
    {
        $gaps = array_filter(array_map(fn ($id) => $this->gap($store, (int) $id), array_slice(array_unique($productIds), 0, 20)));

        return $gaps ? min($gaps) : null;
    }

    /** One product's typical days between a customer's purchases (median), cached for a day. */
    public function gap(Store $store, int $productId): ?int
    {
        if ($productId <= 0) {
            return null;
        }

        return Cache::remember("reorder-gap:{$store->id}:{$productId}", 86400, function () use ($store, $productId) {
            $rows = ProfitOrderLine::query()->join('profit_orders', 'profit_orders.id', '=', 'profit_order_lines.profit_order_id')
                ->where('profit_order_lines.store_id', $store->id)->where('profit_order_lines.product_id', $productId)
                ->where('profit_orders.excluded', false)->whereNotNull('profit_orders.customer_id')
                ->orderBy('profit_orders.day')->get(['profit_orders.customer_id as customer', 'profit_orders.day as day']);
            $gaps = [];
            foreach ($rows->groupBy('customer') as $purchases) {
                $days = $purchases->pluck('day')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->values();
                for ($i = 1; $i < $days->count(); $i++) {
                    $gap = (int) round((strtotime($days[$i]) - strtotime($days[$i - 1])) / 86400);
                    if ($gap > 0) {
                        $gaps[] = $gap;
                    }
                }
            }
            if (count($gaps) < self::MIN_REPEATS) {
                return null;
            }
            sort($gaps);
            $mid = intdiv(count($gaps), 2);

            return (int) round(count($gaps) % 2 ? $gaps[$mid] : ($gaps[$mid - 1] + $gaps[$mid]) / 2);
        });
    }
}
