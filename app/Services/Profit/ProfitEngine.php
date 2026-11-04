<?php

namespace App\Services\Profit;

use App\Models\Profit\AdExpense;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitDaily;
use App\Models\Profit\ProfitOrder;
use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Profit for each day (G3): recalculated from the synced orders, product costs and the store's
 * rules whenever any of them change, so changing a rule recalculates history.
 *
 *   net sales     = items at their price − discounts − refunded items (all before tax)
 *   gross profit  = net sales − cost of goods (units kept × unit cost)
 *   contribution  = gross profit + shipping charged − payment fees − shipping cost
 *   profit        = contribution − ad spend − fixed costs (monthly costs spread over the month's days)
 *
 * Taxes are left out (they're not the store's money). Refunds count on the order's day. Cancelled
 * and test orders are left out. Lines without a cost count in "sales without a cost".
 */
class ProfitEngine
{
    /** Recalculates the days between $from and $to (inclusive, store dates). */
    public function recalculate(Store $store, string $from, string $to): void
    {
        $settings = ProfitSetting::for($store->id);
        $costs = $this->costs($store);
        $orders = ProfitOrder::with('lines')->where('store_id', $store->id)->whereBetween('day', [$from, $to])->where('excluded', false)->get()->groupBy(fn ($o) => substr((string) $o->day, 0, 10));
        $ads = AdExpense::where('store_id', $store->id)->get();

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $day = $date->toDateString();
            $row = array_fill_keys(['gross', 'discounts', 'refunded', 'net_sales', 'shipping', 'cogs', 'missing_cost_sales', 'fees', 'shipping_cost'], 0.0) + ['orders' => 0, 'units' => 0];
            foreach ($orders[$day] ?? [] as $order) {
                $o = $this->order($order, $costs, $settings);
                foreach ($o as $k => $v) {
                    $row[$k] += $v;
                }
                $row['orders']++;
            }
            $row['ad_spend'] = $this->adSpend($ads, $date);
            $row['fixed_costs'] = $this->fixedCosts($settings, $date);
            $row['gross_profit'] = $row['net_sales'] - $row['cogs'];
            $row['contribution'] = $row['gross_profit'] + $row['shipping'] - $row['fees'] - $row['shipping_cost'];
            $row['profit'] = $row['contribution'] - $row['ad_spend'] - $row['fixed_costs'];
            $row = array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $row);

            if ($row['orders'] === 0 && $row['ad_spend'] == 0 && $row['fixed_costs'] == 0) {
                ProfitDaily::where('store_id', $store->id)->where('day', $day)->delete();
            } else {
                ProfitDaily::updateOrCreate(['store_id' => $store->id, 'day' => $day], $row);
            }
        }
    }

    /** Every day that has orders, ad spend or fixed costs, from the first order to today. */
    public function recalculateAll(Store $store): void
    {
        $first = ProfitOrder::where('store_id', $store->id)->min('day');
        if ($first) {
            $this->recalculate($store, Carbon::parse($first)->toDateString(), now($store->timezone ?: 'UTC')->toDateString());
        }
    }

    /**
     * One order's share of a day: what it sold, what it cost, its fees.
     *
     * @return array<string, float|int>
     */
    public function order(ProfitOrder $order, array $costs, ProfitSetting $settings): array
    {
        $cogs = 0.0;
        $missing = 0.0;
        $units = 0;
        foreach ($order->lines as $line) {
            $kept = max(0, $line->quantity - $line->refunded_quantity);
            $units += $kept;
            $cost = $this->unitCost($line, $costs, $settings);
            $value = $line->price * $line->quantity - $line->discount - $line->refunded;
            if ($cost === null) {
                $missing += max(0, $value);
            } else {
                $cogs += $kept * $cost;
            }
        }
        $fees = $this->fees($order, $settings);
        $shippingRule = (array) ($settings->shipping ?? []);
        $shippingCost = match ($shippingRule['mode'] ?? 'none') {
            'per_order' => (float) ($shippingRule['value'] ?? 0),
            'per_item' => (float) ($shippingRule['value'] ?? 0) * $units,
            'percent' => $order->shipping * (float) ($shippingRule['value'] ?? 0) / 100,
            default => 0.0,
        };

        return [
            'gross' => $order->gross, 'discounts' => $order->discounts, 'refunded' => $order->refunded,
            'net_sales' => $order->gross - $order->discounts - $order->refunded, 'shipping' => $order->shipping,
            'cogs' => $cogs, 'missing_cost_sales' => $missing, 'fees' => $fees, 'shipping_cost' => $shippingCost, 'units' => $units,
        ];
    }

    /** Payment fee: the country's rule, else the store's (percent of what was charged + a fixed amount). */
    public function fees(ProfitOrder $order, ProfitSetting $settings): float
    {
        $rule = (array) ($settings->fees ?? []);
        $country = $order->country ? ($rule['countries'][$order->country] ?? null) : null;
        $percent = (float) ($country['percent'] ?? $rule['percent'] ?? 0);
        $fixed = (float) ($country['fixed'] ?? $rule['fixed'] ?? 0);

        return $order->total > 0 ? $order->total * $percent / 100 + $fixed : 0.0;
    }

    /** variant id => cost used (Growvia's own, else Shopify's). */
    public function costs(Store $store): array
    {
        return ProductCost::where('store_id', $store->id)->get()->mapWithKeys(fn ($c) => [(int) $c->variant_id => $c->effective()])->filter(fn ($v) => $v !== null)->all();
    }

    private function unitCost($line, array $costs, ProfitSetting $settings): ?float
    {
        if ($line->variant_id && array_key_exists((int) $line->variant_id, $costs)) {
            return (float) $costs[(int) $line->variant_id];
        }

        return $settings->default_cost_percent !== null ? $line->price * $settings->default_cost_percent / 100 : null;
    }

    /** A day's ad spend: each entry spread evenly over its days; a recurring entry per month. */
    public function adSpend($ads, Carbon $date): float
    {
        $total = 0.0;
        foreach ($ads as $ad) {
            if ($ad->recurring) {
                $start = $ad->starts_on->copy()->startOfMonth();
                $end = $ad->ends_on?->copy()->endOfMonth();
                if ($date->lt($start) || ($end && $date->gt($end))) {
                    continue;
                }
                $total += $ad->amount / $date->daysInMonth;
            } else {
                $end = $ad->ends_on ?? $ad->starts_on;
                if ($date->lt($ad->starts_on) || $date->gt($end)) {
                    continue;
                }
                $total += $ad->amount / ($ad->starts_on->diffInDays($end) + 1);
            }
        }

        return $total;
    }

    /** Fixed monthly costs (apps, staff, rent) spread over the month's days. */
    public function fixedCosts(ProfitSetting $settings, Carbon $date): float
    {
        $monthly = array_sum(array_map(fn ($c) => (float) ($c['amount'] ?? 0), (array) ($settings->fixed_costs ?? [])));

        return $monthly / $date->daysInMonth;
    }
}
