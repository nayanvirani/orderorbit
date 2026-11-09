<?php

namespace App\Services\Insights;

use App\Models\Profit\ProfitOrder;
use App\Models\Profit\ProfitOrderLine;
use App\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Customer value insights (G19), from order data only (customer ids, never names): repeat rate,
 * typical time to a second order and net sales per customer over 12 months, how many of each
 * month's new customers ordered again in each month after, and the first products whose buyers
 * came back the most.
 */
class CustomerValue
{
    /** A first product needs at least this many first-time buyers to be ranked. */
    public const MIN_BUYERS = 5;

    public function build(Store $store): array
    {
        $tz = $store->timezone ?: 'UTC';
        $today = now($tz)->startOfDay();
        $since = $today->copy()->subMonthsNoOverflow(12)->addDay()->toDateString();

        // Every synced order with a customer: [id, customer, day, net sales].
        $orders = ProfitOrder::where('store_id', $store->id)->where('excluded', false)->whereNotNull('customer_id')
            ->orderBy('day')->orderBy('id')->get(['id', 'customer_id', 'day', 'gross', 'discounts', 'refunded'])
            ->map(fn ($o) => ['id' => $o->id, 'customer' => (int) $o->customer_id, 'day' => substr((string) $o->day, 0, 10),
                'net' => round((float) $o->gross - (float) $o->discounts - (float) $o->refunded, 2)]);
        $byCustomer = $orders->groupBy('customer');

        // Last 12 months.
        $recent = $orders->filter(fn ($o) => $o['day'] >= $since);
        $customers = $recent->pluck('customer')->unique();
        $repeaters = $recent->groupBy('customer')->filter(fn ($o) => $o->count() >= 2)->count();
        $gaps = $byCustomer->filter(fn ($o) => $o->count() >= 2)
            ->map(fn ($o) => Carbon::parse($o[0]['day'])->diffInDays(Carbon::parse($o[1]['day'])))->sort()->values();
        $summary = [
            'customers' => $customers->count(),
            'repeat_rate' => $customers->count() ? (int) round($repeaters / $customers->count() * 100) : null,
            'second_order_days' => $gaps->isEmpty() ? null : (int) round($gaps->median()),
            'value' => $customers->count() ? round($recent->sum('net') / $customers->count(), 2) : null,
        ];

        // Cohorts: the last 6 complete months of first orders, and the share ordering again in each month after.
        $first = $byCustomer->map(fn ($o) => $o[0]);
        $cohorts = [];
        for ($i = 6; $i >= 1; $i--) {
            $month = $today->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $key = $month->format('Y-m');
            $members = $first->filter(fn ($o) => substr($o['day'], 0, 7) === $key)->keys();
            $cells = [];
            for ($k = 1; $k <= 6; $k++) {
                $target = $month->copy()->addMonthsNoOverflow($k);
                if ($target->gte($today->copy()->startOfMonth())) {
                    $cells[] = null; // hasn't happened (or isn't complete) yet

                    continue;
                }
                $tk = $target->format('Y-m');
                $back = $members->filter(fn ($c) => $byCustomer[$c]->contains(fn ($o) => substr($o['day'], 0, 7) === $tk))->count();
                $cells[] = $members->count() ? (int) round($back / $members->count() * 100) : null;
            }
            $cohorts[] = ['month' => $month->format('M Y'), 'customers' => $members->count(), 'cells' => $cells];
        }

        // Best first products: of customers whose first order had the product, how many came back,
        // and their net sales over all their orders.
        $firstOrderIds = $first->pluck('id')->all();
        $lines = ProfitOrderLine::whereIn('profit_order_id', $firstOrderIds ?: [0])->get(['profit_order_id', 'product_id', 'title']);
        $orderCustomer = $first->mapWithKeys(fn ($o, $c) => [$o['id'] => $c]);
        $products = [];
        foreach ($lines as $l) {
            $key = $l->product_id ?: 'title:'.$l->title;
            $products[$key] ??= ['name' => explode(' - ', (string) $l->title)[0], 'customers' => []];
            $products[$key]['customers'][$orderCustomer[$l->profit_order_id]] = true;
        }
        $best = collect($products)->map(function ($p) use ($byCustomer) {
            $ids = array_keys($p['customers']);
            $back = count(array_filter($ids, fn ($c) => $byCustomer[$c]->count() >= 2));

            return ['name' => $p['name'], 'buyers' => count($ids), 'return_rate' => (int) round($back / count($ids) * 100),
                'value' => round(collect($ids)->sum(fn ($c) => $byCustomer[$c]->sum('net')) / count($ids), 2)];
        })->filter(fn ($p) => $p['buyers'] >= self::MIN_BUYERS)->sortByDesc('return_rate')->take(5)->values()->all();

        return ['summary' => $summary, 'cohorts' => $cohorts, 'best' => $best, 'min_buyers' => self::MIN_BUYERS];
    }
}
