<?php

namespace App\Services\Protect;

use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitOrder;
use App\Models\Profit\ProfitOrderLine;
use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Services\Profit\ProfitEngine;
use App\Services\Profit\ProfitReports;
use Illuminate\Support\Collection;

/**
 * Protect (phase 4): days of stock (G8), product data gaps (G9) and the numbers the money-leak
 * alerts (G7) check. Everything comes from Profit's synced orders and the variant sync, so it
 * agrees with the Profit pages.
 */
class Protect
{
    public const DEFAULTS = ['lead_days' => 14, 'cover_days' => 45, 'rules' => []];

    public function __construct(private readonly ProfitEngine $engine, private readonly ProfitReports $reports) {}

    public static function settings(Store $store): array
    {
        return array_merge(self::DEFAULTS, (array) (ProfitSetting::for($store->id)->protect ?? []));
    }

    /** Variants that are on sale: active products still in Shopify. */
    public function variants(Store $store): Collection
    {
        // Only variants read with their product details (seen_at): before that, image, SKU and the
        // rest aren't known yet and would all look missing.
        return ProductCost::where('store_id', $store->id)->whereNotNull('seen_at')->where('product_status', 'ACTIVE')->get();
    }

    /**
     * variant id => [units kept, net sales] over the last $days days.
     *
     * @return array<int, array{0: int, 1: float}>
     */
    public function sales(Store $store, int $days = 30): array
    {
        return ProfitOrderLine::query()->join('profit_orders', 'profit_orders.id', '=', 'profit_order_lines.profit_order_id')
            ->where('profit_order_lines.store_id', $store->id)->where('profit_orders.excluded', false)
            ->where('profit_orders.day', '>=', now($store->timezone ?: 'UTC')->subDays($days - 1)->toDateString())
            ->selectRaw('profit_order_lines.variant_id as v, sum(profit_order_lines.quantity - profit_order_lines.refunded_quantity) as units, sum(profit_order_lines.price * profit_order_lines.quantity - profit_order_lines.discount - profit_order_lines.refunded) as sales')
            ->groupBy('profit_order_lines.variant_id')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->v => [(int) $r->units, round((float) $r->sales, 2)]])->all();
    }

    /** The 20 variants with the most sales in 30 days (variant ids). */
    public function bestSellers(array $sales): array
    {
        $sorted = array_filter($sales, fn ($s) => $s[1] > 0);
        uasort($sorted, fn ($a, $b) => $b[1] <=> $a[1]);

        return array_slice(array_keys($sorted), 0, 20);
    }

    /**
     * Days of stock per tracked variant (G8): sold per day from the last 30 days, days left, the date
     * to reorder by (days left − supplier lead time) and a quantity that covers the cover days plus
     * the lead time.
     */
    public function inventory(Store $store, ?string $location = null): array
    {
        $s = self::settings($store);
        $sales = $this->sales($store);
        $today = now($store->timezone ?: 'UTC')->startOfDay();
        $rows = [];
        foreach ($this->variants($store) as $v) {
            if (! $v->tracked) {
                continue;
            }
            $available = $location ? (int) (($v->locations[$location][1] ?? 0)) : (int) ($v->available ?? 0);
            if ($location && ! isset($v->locations[$location])) {
                continue;
            }
            $rate = round(($sales[(int) $v->variant_id][0] ?? 0) / 30, 2);
            $days = $rate > 0 ? (int) floor(max(0, $available) / $rate) : null;
            $status = match (true) {
                // Sold out matters only for what was selling; out of stock and not selling is "no sales".
                $available <= 0 && $rate > 0 => 'sold_out',
                $rate <= 0 => 'no_sales',
                $days - $s['lead_days'] <= 7 => 'reorder',
                default => 'healthy',
            };
            $rows[] = [
                'variant_id' => (int) $v->variant_id, 'name' => $v->title, 'sku' => $v->sku, 'available' => $available, 'rate' => $rate, 'days' => $days,
                // Over a year of stock: no reorder date (a date years away isn't useful).
                'reorder_by' => $days !== null && $days <= 365 ? $today->copy()->addDays(max(0, $days - $s['lead_days']))->toDateString() : null,
                'over_year' => $days !== null && $days > 365,
                'qty' => $rate > 0 && ($days === null || $days <= 365) ? max(0, (int) ceil($rate * ($s['cover_days'] + $s['lead_days']) - max(0, $available))) : 0,
                'status' => $status, 'stock_value' => $available > 0 && $v->effective() !== null ? round($available * $v->effective(), 2) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => [$a['status'] !== 'reorder', $a['status'] !== 'sold_out', $a['days'] ?? PHP_INT_MAX] <=> [$b['status'] !== 'reorder', $b['status'] !== 'sold_out', $b['days'] ?? PHP_INT_MAX]);

        return $rows;
    }

    /**
     * Product data gaps on active products (G9), best sellers first: missing image (or images on some
     * variants), description, price, SKU, barcode, weight and cost.
     */
    public function productData(Store $store): array
    {
        $sales = $this->sales($store);
        $products = [];
        foreach ($this->variants($store)->groupBy('product_id') as $productId => $variants) {
            $issues = [];
            $count = fn (callable $test) => $variants->filter($test)->count();
            $label = fn (string $what, int $n) => $n === $variants->count() ? "No {$what}" : "No {$what} on {$n} ".($n === 1 ? 'variant' : 'variants');
            foreach ([
                'image' => [fn ($v) => ! $v->has_image, 'image'],
                'price' => [fn ($v) => ! $v->price || $v->price <= 0, 'price'],
                'sku' => [fn ($v) => ! $v->sku, 'SKU'],
                'barcode' => [fn ($v) => ! $v->barcode, 'barcode'],
                'weight' => [fn ($v) => ! $v->weight, 'weight'],
                'cost' => [fn ($v) => $v->effective() === null, 'cost'],
            ] as $key => [$test, $what]) {
                if ($n = $count($test)) {
                    $issues[$key] = $label($what, $n);
                }
            }
            if (! $variants->first()->has_description) {
                $issues['description'] = 'No description';
            }
            if ($issues) {
                $products[] = [
                    'product_id' => (int) $productId, 'name' => explode(' - ', (string) $variants->first()->title)[0],
                    'sales' => round($variants->sum(fn ($v) => $sales[(int) $v->variant_id][1] ?? 0), 2), 'issues' => $issues,
                    'url' => $store->adminUrl('products/'.$productId),
                ];
            }
        }
        usort($products, fn ($a, $b) => $b['sales'] <=> $a['sales']);

        return $products;
    }

    /** Orders in the last $days days with their contribution (net sales − cost − fees + shipping − shipping cost). */
    public function orderContributions(Store $store, int $days = 30): Collection
    {
        $settings = ProfitSetting::for($store->id);
        $costs = $this->engine->costs($store);

        return ProfitOrder::with('lines')->where('store_id', $store->id)->where('excluded', false)
            ->where('day', '>=', now($store->timezone ?: 'UTC')->subDays($days - 1)->toDateString())->get()
            ->map(function ($o) use ($costs, $settings) {
                $x = $this->engine->order($o, $costs, $settings);
                $o->contribution = $x['net_sales'] - $x['cogs'] + $x['shipping'] - $x['fees'] - $x['shipping_cost'];
                $o->costed = $x['missing_cost_sales'] <= 0;

                return $o;
            });
    }

    public function reports(): ProfitReports
    {
        return $this->reports;
    }
}
