<?php

namespace App\Services\Insights;

use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\Profit\AdExpense;
use App\Models\Protect\ProtectAlert;
use App\Models\Store;
use App\Services\Profit\ProfitReports;
use Illuminate\Support\Carbon;

/**
 * Monthly Business Report (G15): a calendar month's revenue, profit, margin and orders against
 * the month before, what Growvia's offers earned, the top products by profit, open problems and
 * 3 things to do next. The numbers come from the same daily summary as the Profit dashboard, so
 * they match it for the same dates.
 */
class MonthlyReport
{
    public function __construct(private readonly ProfitReports $reports) {}

    /** Months with a report: the last 12 complete months, newest first ("2026-10" => "October 2026"). */
    public function months(Store $store): array
    {
        $start = now($store->timezone ?: 'UTC')->startOfMonth();
        $out = [];
        for ($i = 1; $i <= 12; $i++) {
            $m = $start->copy()->subMonthsNoOverflow($i);
            $out[$m->format('Y-m')] = $m->format('F Y');
        }

        return $out;
    }

    /** The report for "YYYY-MM" (the last complete month when null or not in the list). */
    public function build(Store $store, ?string $month = null): array
    {
        $months = $this->months($store);
        $month = isset($months[$month]) ? $month : array_key_first($months);
        $tz = $store->timezone ?: 'UTC';
        $from = Carbon::createFromFormat('Y-m-d', $month.'-01', $tz)->startOfDay();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $prevFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $prevTo = $prevFrom->copy()->endOfMonth()->startOfDay();
        $t = $this->reports->totals($store, $from, $to);
        $p = $this->reports->totals($store, $prevFrom, $prevTo);
        $ads = AdExpense::where('store_id', $store->id)->exists();
        $prevName = $prevFrom->format('F');
        $profitKey = $ads ? 'after_ads' : 'contribution';
        $profitLabel = $ads ? 'Profit after ads' : 'Contribution';

        $kpis = [
            ['Net sales', $t['net_sales'], $this->change($t['net_sales'], $p['net_sales'], $prevName), 'money'],
            [$profitLabel, $t[$profitKey], $this->change($t[$profitKey], $p[$profitKey], $prevName), 'money'],
            [$ads ? 'Margin after ads' : 'Contribution margin', $ads ? $t['margin'] : $t['contribution_margin'],
                $this->points($ads ? $t['margin'] : $t['contribution_margin'], $ads ? $p['margin'] : $p['contribution_margin']), 'percent'],
            ['Orders', $t['orders'], $this->change($t['orders'], $p['orders'], $prevName), 'number'],
        ];

        // What each Growvia offer added (direct offer revenue: the order lines it put in the cart).
        $earned = AnalyticsEvent::where('store_id', $store->id)->where('event', 'attributed')->whereNotNull('experience_handle')
            ->whereBetween('occurred_at', [$from->copy()->utc(), $to->copy()->endOfDay()->utc()])
            ->selectRaw('experience_handle as handle, coalesce(sum(value), 0) as total')->groupBy('experience_handle')->orderByDesc('total')->get();
        $names = Experience::where('store_id', $store->id)->whereIn('handle', $earned->pluck('handle'))->pluck('name', 'handle');
        $widgets = $earned->filter(fn ($r) => (float) $r->total > 0)->take(6)
            ->map(fn ($r) => ['name' => $names[$r->handle] ?? $r->handle, 'value' => round((float) $r->total, 2)])->values()->all();
        $widgetTotal = round((float) $earned->sum('total'), 2);

        $top = collect($this->reports->breakdown($store, $from, $to, 'product'))->filter(fn ($r) => ! $r['missing_cost'] && $r['contribution'] > 0)
            ->sortByDesc('contribution')->take(3)->map(fn ($r) => ['name' => $r['name'], 'value' => $r['contribution'], 'margin' => $r['contribution_margin']])->values()->all();

        // Open problems now and the 3 things worth doing next (from Protect, problems first).
        $open = ProtectAlert::where('store_id', $store->id)->where('status', 'open')->get();
        $problems = $open->where('severity', '!=', 'idea')->count();
        $todo = $open->sortBy(fn ($a) => [['urgent' => 0, 'warning' => 1, 'idea' => 2][$a->severity] ?? 3, -$a->cost])->take(3)
            ->map(fn ($a) => ['title' => $a->title, 'detail' => trim($a->what.' '.$a->fix)])->values()->all();

        return [
            'month' => $month, 'label' => $from->format('F Y'), 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'next' => $to->copy()->addDay()->format('F'),
            'store' => $store->name ?: $store->shop_domain, 'currency' => $store->currency ?: 'USD', 'months' => $months,
            'summary' => $this->summary($t, $p, $ads, $prevName, $widgetTotal),
            'kpis' => array_map(fn ($k) => ['label' => $k[0], 'value' => $k[1], 'change' => $k[2], 'kind' => $k[3]], $kpis),
            'widgets' => $widgets, 'widget_total' => $widgetTotal, 'widget_share' => $t['net_sales'] > 0 ? round($widgetTotal / $t['net_sales'] * 100, 1) : null,
            'top' => $top, 'problems' => $problems, 'todo' => $todo,
            'missing_share' => $t['net_sales'] > 0 ? round($t['missing_cost_sales'] / $t['net_sales'] * 100) : 0,
            'has_sales' => $t['orders'] > 0, 'ads' => $ads,
        ];
    }

    /** Two plain sentences about the month. */
    private function summary(array $t, array $p, bool $ads, string $prev, float $widgets): string
    {
        if ($t['orders'] === 0) {
            return 'No orders this month.';
        }
        $profitNow = $ads ? $t['after_ads'] : $t['contribution'];
        $profitBefore = $ads ? $p['after_ads'] : $p['contribution'];
        $sales = $this->pct($t['net_sales'], $p['net_sales']);
        $profit = $this->pct($profitNow, $profitBefore);
        $parts = [];
        $parts[] = $sales === null ? 'Your first month with sales in Growvia.'
            : 'Net sales '.($sales >= 0 ? "rose {$sales}%" : 'fell '.abs($sales).'%')." from {$prev}"
                .($profit === null ? '.' : ', and '.($ads ? 'profit after ads' : 'contribution').' '.($profit >= 0 ? "rose {$profit}%." : 'fell '.abs($profit).'%.'));
        if ($ads && $p['ad_spend'] > 0 && ($ad = $this->pct($t['ad_spend'], $p['ad_spend'])) !== null && abs($ad) >= 15) {
            $parts[] = 'Ad spend '.($ad > 0 ? "rose {$ad}%" : 'fell '.abs($ad).'%').'.';
        }
        if ($widgets > 0) {
            $parts[] = 'Growvia\'s offers added '.number_format($widgets, 2).' in sales.';
        }

        return implode(' ', $parts);
    }

    private function pct(float $now, float $before): ?int
    {
        return abs($before) < 1 ? null : (int) round(($now - $before) / abs($before) * 100);
    }

    private function change(float $now, float $before, string $prev): ?string
    {
        $pct = $this->pct($now, $before);

        return $pct === null || abs($pct) > 999 ? null : ($pct >= 0 ? '+' : '−').abs($pct)."% vs {$prev}";
    }

    private function points(?float $now, ?float $before): ?string
    {
        return $now === null || $before === null ? null : ($now >= $before ? '+' : '−').number_format(abs($now - $before), 1).' pts';
    }
}
