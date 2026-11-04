<?php

namespace App\Services\Profit;

use App\Models\Profit\AdExpense;
use App\Models\Profit\ProfitDaily;
use App\Models\Profit\ProfitOrder;
use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * The numbers behind the Profit pages, all from the same daily summary and synced orders, so every
 * page agrees. Profit levels: net sales → gross profit (− product cost) → contribution (− fees and
 * shipping) → profit after ads (− ad spend) → estimated net (− fixed costs).
 */
class ProfitReports
{
    public function __construct(private readonly ProfitEngine $engine) {}

    /** [from, to] store dates for a range key: 30 | this_month | last_month | 90 | 365. */
    public static function range(Store $store, string $key): array
    {
        $today = now($store->timezone ?: 'UTC')->startOfDay();

        return match ($key) {
            'this_month' => [$today->copy()->startOfMonth(), $today],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            '90' => [$today->copy()->subDays(89), $today],
            '365' => [$today->copy()->subDays(364), $today],
            default => [$today->copy()->subDays(29), $today],
        };
    }

    /** Totals for a range, from the daily summary. */
    public function totals(Store $store, Carbon $from, Carbon $to): array
    {
        $cols = ['orders', 'units', 'gross', 'discounts', 'refunded', 'net_sales', 'shipping', 'cogs', 'missing_cost_sales', 'fees', 'shipping_cost', 'ad_spend', 'fixed_costs', 'gross_profit', 'contribution', 'profit'];
        $row = ProfitDaily::where('store_id', $store->id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(implode(', ', array_map(fn ($c) => "coalesce(sum({$c}), 0) as {$c}", $cols)))->first();
        $t = array_map(fn ($c) => round((float) ($row->{$c} ?? 0), 2), array_combine($cols, $cols));
        $t['orders'] = (int) $t['orders'];
        $t['units'] = (int) $t['units'];
        $t['fees_and_shipping'] = round($t['fees'] + $t['shipping_cost'] - $t['shipping'], 2);
        $t['after_ads'] = round($t['contribution'] - $t['ad_spend'], 2);
        $t['estimated_net'] = $t['profit'];
        $t['margin'] = $t['net_sales'] > 0 ? round($t['after_ads'] / $t['net_sales'] * 100, 1) : null;
        $t['contribution_margin'] = $t['net_sales'] > 0 ? round($t['contribution'] / $t['net_sales'] * 100, 1) : null;

        return $t;
    }

    /** day => profit after ads, for the trend. */
    public function daily(Store $store, Carbon $from, Carbon $to): array
    {
        $rows = ProfitDaily::where('store_id', $store->id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])->get()
            ->keyBy(fn ($r) => substr((string) $r->day, 0, 10));
        $out = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $r = $rows[$d->toDateString()] ?? null;
            $out[] = ['day' => $d->toDateString(), 'sales' => $r ? $r->net_sales : 0, 'after_ads' => $r ? round($r->contribution - $r->ad_spend, 2) : 0];
        }

        return $out;
    }

    /**
     * Rows grouped by product, variant, order, channel, country or day, with ad spend split by the
     * expenses' product tags and untagged spend shared by sales.
     *
     * @return list<array>
     */
    public function breakdown(Store $store, Carbon $from, Carbon $to, string $by): array
    {
        $settings = ProfitSetting::for($store->id);
        $costs = $this->engine->costs($store);
        $orders = ProfitOrder::with('lines')->where('store_id', $store->id)->where('excluded', false)
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])->get();

        $rows = [];
        $add = function (string $key, string $name, array $v, int $orderId) use (&$rows) {
            $rows[$key] ??= ['key' => $key, 'name' => $name, 'orders' => [], 'units' => 0, 'net_sales' => 0.0, 'cogs' => 0.0, 'fees_and_shipping' => 0.0, 'missing_cost_sales' => 0.0, 'product_ids' => []];
            $rows[$key]['orders'][$orderId] = true;
            foreach (['units', 'net_sales', 'cogs', 'fees_and_shipping', 'missing_cost_sales'] as $k) {
                $rows[$key][$k] += $v[$k];
            }
            if (! empty($v['product_id'])) {
                $rows[$key]['product_ids'][$v['product_id']] = true;
            }
        };

        foreach ($orders as $order) {
            $o = $this->engine->order($order, $costs, $settings);
            $orderFees = $o['fees'] + $o['shipping_cost'] - $o['shipping'];
            $orderNet = max(0.01, $o['net_sales']);
            if (in_array($by, ['product', 'variant'], true)) {
                foreach ($order->lines as $line) {
                    $net = $line->price * $line->quantity - $line->discount - $line->refunded;
                    $kept = max(0, $line->quantity - $line->refunded_quantity);
                    $cost = array_key_exists((int) $line->variant_id, $costs) ? (float) $costs[(int) $line->variant_id]
                        : ($settings->default_cost_percent !== null ? $line->price * $settings->default_cost_percent / 100 : null);
                    $key = $by === 'product' ? 'p'.$line->product_id : 'v'.$line->variant_id;
                    $name = $by === 'product' ? (string) $line->title : trim($line->title.($line->variant_title ? ' · '.$line->variant_title : ''));
                    $add($key, $name ?: 'Unknown product', [
                        'units' => $kept, 'net_sales' => $net, 'cogs' => $cost === null ? 0 : $kept * $cost, 'missing_cost_sales' => $cost === null ? max(0, $net) : 0,
                        // The order's fees and shipping, shared by its lines' sales.
                        'fees_and_shipping' => $orderFees * $net / $orderNet, 'product_id' => $line->product_id,
                    ], $order->order_id);
                }
                continue;
            }
            [$key, $name] = match ($by) {
                'order' => ['o'.$order->order_id, (string) ($order->name ?: $order->order_id)],
                'channel' => ['c'.($order->channel ?: 'unknown'), self::channel($order->channel)],
                'country' => ['k'.($order->country ?: '--'), $order->country ?: 'Unknown'],
                default => ['d'.substr((string) $order->day, 0, 10), substr((string) $order->day, 0, 10)],
            };
            $add($key, $name, ['units' => $o['units'], 'net_sales' => $o['net_sales'], 'cogs' => $o['cogs'], 'missing_cost_sales' => $o['missing_cost_sales'], 'fees_and_shipping' => $orderFees], $order->order_id);
        }

        // Ad spend: tagged spend to its products, the rest shared by sales (by day, exactly).
        [$tagged, $untagged] = $this->adSplit($store, $from, $to);
        $totalSales = max(0.01, array_sum(array_column($rows, 'net_sales')));
        $daySpend = $by === 'day' ? ProfitDaily::where('store_id', $store->id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])->pluck('ad_spend', 'day')->mapWithKeys(fn ($v, $k) => [substr((string) $k, 0, 10) => (float) $v])->all() : [];

        return collect($rows)->map(function ($r) use ($tagged, $untagged, $totalSales, $by, $daySpend) {
            $ads = match ($by) {
                'day' => $daySpend[$r['name']] ?? 0,
                'product', 'variant' => array_sum(array_map(fn ($p) => $tagged[$p] ?? 0, array_keys($r['product_ids']))) + $untagged * max(0, $r['net_sales']) / $totalSales,
                default => ($untagged + array_sum($tagged)) * max(0, $r['net_sales']) / $totalSales,
            };
            $contribution = $r['net_sales'] - $r['cogs'] - $r['fees_and_shipping'];
            $after = $contribution - $ads;

            return [
                'key' => $r['key'], 'name' => $r['name'], 'orders' => count($r['orders']), 'units' => $r['units'],
                'net_sales' => round($r['net_sales'], 2), 'cogs' => round($r['cogs'], 2), 'fees_and_shipping' => round($r['fees_and_shipping'], 2),
                'contribution' => round($contribution, 2), 'ad_spend' => round($ads, 2), 'after_ads' => round($after, 2),
                'margin' => $r['net_sales'] > 0 ? round($after / $r['net_sales'] * 100, 1) : null,
                'contribution_margin' => $r['net_sales'] > 0 ? round($contribution / $r['net_sales'] * 100, 1) : null,
                'tagged_spend' => round(array_sum(array_map(fn ($p) => $tagged[$p] ?? 0, array_keys($r['product_ids']))), 2),
                'missing_cost' => $r['missing_cost_sales'] > 0,
            ];
        })->sortByDesc($by === 'day' ? 'name' : 'net_sales')->values()->all();
    }

    /**
     * Ad spend in the range: [product id => spend tagged to it, untagged spend]. Spend tagged to
     * several products is split evenly; collection tags count as untagged (shared by sales).
     */
    public function adSplit(Store $store, Carbon $from, Carbon $to): array
    {
        $tagged = [];
        $untagged = 0.0;
        $ads = AdExpense::where('store_id', $store->id)->get();
        foreach ($ads as $ad) {
            $spend = 0.0;
            foreach (CarbonPeriod::create($from, $to) as $d) {
                $spend += $this->engine->adSpend([$ad], $d);
            }
            $products = array_values(array_filter(array_map(fn ($t) => ($t['type'] ?? '') === 'product' ? (int) \Illuminate\Support\Str::afterLast((string) ($t['id'] ?? ''), '/') : null, (array) $ad->tags)));
            if ($products) {
                foreach ($products as $p) {
                    $tagged[$p] = ($tagged[$p] ?? 0) + $spend / count($products);
                }
            } else {
                $untagged += $spend;
            }
        }

        return [$tagged, $untagged];
    }

    public static function channel(?string $source): string
    {
        return match ($source) {
            'web' => 'Online store', 'pos' => 'Point of sale', 'shopify_draft_order' => 'Draft orders', 'iphone', 'android' => 'Shopify mobile',
            null, '' => 'Unknown', default => ucfirst(str_replace('_', ' ', $source)),
        };
    }
}
