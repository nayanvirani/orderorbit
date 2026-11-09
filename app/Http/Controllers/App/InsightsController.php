<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Profit\ProfitOrder;
use App\Models\Store;
use App\Services\Profit\ProfitReports;
use App\Services\Profit\ProfitSync;
use App\Support\Features;
use App\Support\Spa\Page;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Insights › Sales (G14): sales, orders, average order and returning customers, weekly sales by new
 * and returning customers, products by profit and sales by country. It reads the same synced orders
 * and daily summary as Profit, so the two always agree.
 */
class InsightsController extends Controller
{
    private const RANGES = ['30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months'];

    public function __construct(private readonly ProfitReports $reports) {}

    public function sales(Request $request, Store $store): Page
    {
        abort_unless(Features::enabled('sales_insights', $store) && Features::enabled('profit', $store), 404);
        $range = array_key_exists((string) $request->query('range'), self::RANGES) ? (string) $request->query('range') : '30';
        [$from, $to] = ProfitReports::range($store, $range);
        $oldest = now($store->timezone ?: 'UTC')->startOfDay()->subDays(ProfitSync::historyDays($store) - 1);
        $from = $from->lt($oldest) ? $oldest : $from;
        $days = $from->diffInDays($to) + 1;

        $now = $this->numbers($store, $from, $to);
        $before = $this->numbers($store, $from->copy()->subDays($days), $from->copy()->subDay());
        $products = $this->reports->breakdown($store, $from, $to, 'product');
        $countries = $this->reports->breakdown($store, $from, $to, 'country');
        $net = max(0.01, array_sum(array_column($countries, 'net_sales')));

        return page('insights/sales', [
            'range' => $range, 'ranges' => self::RANGES, 'currency' => $store->currency ?: 'USD',
            'now' => $now, 'before' => $before,
            'weeks' => $this->weeks($store, $from, $to),
            'best' => array_slice(collect($products)->sortByDesc('after_ads')->values()->all(), 0, 5),
            'worst' => array_slice(collect($products)->sortBy('after_ads')->values()->all(), 0, 5),
            'countries' => array_map(fn ($c) => ['name' => $c['name'], 'sales' => $c['net_sales'], 'share' => round($c['net_sales'] / $net * 100)], array_slice($countries, 0, 8)),
            'access' => ProfitSync::missingAccess($store) ? ['missing' => ProfitSync::missingAccess($store)] : null,
            'locked' => $store->planIncludes('sales_insights') ? null : \App\Support\Plans::unlockMessage('sales_insights', 'see it', $store),
        ]);
    }

    /** Insights › Monthly report (G15): last month by default, any of the last 12. */
    public function report(Request $request, Store $store, \App\Services\Insights\MonthlyReport $reports): Page
    {
        abort_unless(Features::enabled('monthly_report', $store) && Features::enabled('profit', $store), 404);

        return page('insights/report', [
            'report' => $reports->build($store, (string) $request->query('month')),
            'access' => ProfitSync::missingAccess($store) ? ['missing' => ProfitSync::missingAccess($store)] : null,
            'locked' => $store->planIncludes('sales_insights') ? null : \App\Support\Plans::unlockMessage('sales_insights', 'see it', $store),
        ]);
    }

    public function reportPdf(Request $request, Store $store, \App\Services\Insights\MonthlyReport $reports, \App\Services\Insights\MonthlyReportSender $sender): \Illuminate\Http\Response
    {
        $this->reportGate($store);
        $report = $reports->build($store, (string) $request->query('month'));

        return response($sender->pdf($report), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="growvia-report-'.$report['month'].'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    /** "Email to me": the report to the person asking, at once. */
    public function reportEmail(Request $request, Store $store, \App\Services\Insights\MonthlyReport $reports, \App\Services\Insights\MonthlyReportSender $sender): \Illuminate\Http\RedirectResponse
    {
        $this->reportGate($store);
        $user = $request->attributes->get('storeUser');
        $report = $reports->build($store, (string) $request->input('month'));
        if (! $user?->email) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Your Shopify account has no email address we can send to.']);
        }
        if (! $sender->sendTo($store, $user, $report)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'The email couldn\'t be sent just now. Try again in a few minutes.']);
        }

        return redirect()->to(app_route('app.insights.report', ['month' => $report['month'], 'notice' => 'emailed']));
    }

    private function reportGate(Store $store): void
    {
        abort_unless(Features::enabled('monthly_report', $store) && Features::enabled('profit', $store), 404);
        abort_unless($store->planIncludes('sales_insights'), 403, \App\Support\Plans::unlockMessage('sales_insights', 'use it', $store));
    }

    /** Net sales, orders, average order and the share of orders from returning customers. */
    private function numbers(Store $store, Carbon $from, Carbon $to): array
    {
        $t = $this->reports->totals($store, $from, $to);
        // "returning" is a reserved word in SQL, so it's only used through the query builder.
        $orders = ProfitOrder::where('store_id', $store->id)->where('excluded', false)->whereBetween('day', [$from->toDateString(), $to->toDateString()]);
        $all = (clone $orders)->count();
        $repeat = (clone $orders)->where('returning', true)->count();

        return [
            'net_sales' => $t['net_sales'], 'orders' => $t['orders'], 'aov' => $t['orders'] ? round($t['net_sales'] / $t['orders'], 2) : null,
            'returning' => $all ? round($repeat / $all * 100) : null,
        ];
    }

    /** Net sales per week (Monday start), new and returning customers. */
    private function weeks(Store $store, Carbon $from, Carbon $to): array
    {
        $weeks = [];
        for ($w = $from->copy()->startOfWeek(); $w->lte($to); $w->addWeek()) {
            $weeks[$w->toDateString()] = ['week' => $w->toDateString(), 'new' => 0.0, 'returning' => 0.0];
        }
        ProfitOrder::where('store_id', $store->id)->where('excluded', false)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->get(['day', 'gross', 'discounts', 'refunded', 'returning'])->each(function ($o) use (&$weeks) {
                $week = Carbon::parse(substr((string) $o->day, 0, 10))->startOfWeek()->toDateString();
                if (isset($weeks[$week])) {
                    $weeks[$week][$o->returning ? 'returning' : 'new'] += $o->gross - $o->discounts - $o->refunded;
                }
            });

        return array_values(array_map(fn ($w) => ['week' => $w['week'], 'new' => round($w['new'], 2), 'returning' => round($w['returning'], 2)], $weeks));
    }
}
