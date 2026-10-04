<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Reporting over the web pixel's events: store totals (sessions, orders, revenue,
 * AOV, conversion) and, per experience, views, clicks, adds to cart, orders and
 * the revenue from the lines it added.
 */
class Analytics
{
    public function summary(Store $store, int $days = 30): array
    {
        // Aggregated in the database: stores record every page view, so events aren't loaded.
        $from = now()->subDays($days)->startOfDay();
        $to = now();
        $t = $this->totals($store, $from, $to);
        $influenced = AnalyticsEvent::where('store_id', $store->id)->where('event', 'order')->whereBetween('occurred_at', [$from, $to])
            ->whereIn('order_ref', AnalyticsEvent::select('order_ref')->where('store_id', $store->id)->where('event', 'attributed')->whereBetween('occurred_at', [$from, $to]))
            ->selectRaw('count(*) as n, coalesce(sum(value), 0) as total')->first();

        return [
            'days' => $days,
            'sessions' => $t['sessions'],
            'orders' => $t['orders'],
            'revenue' => $t['revenue'],
            'aov' => $t['aov'],
            'conversion' => $t['conversion'],
            'influenced_orders' => $t['influenced_orders'],
            'influenced_revenue' => $t['influenced_revenue'],
            'influenced_aov' => $influenced->n ? (float) $influenced->total / $influenced->n : null,
            'currency' => AnalyticsEvent::where('store_id', $store->id)->where('event', 'order')->whereNotNull('currency')->latest('id')->value('currency') ?? $store->currency,
            'daily' => $this->daily($store, $from, $days),
            'experiences' => $this->aggregate(AnalyticsEvent::where('store_id', $store->id)->where('occurred_at', '>=', $from)),
            'last_event_at' => AnalyticsEvent::where('store_id', $store->id)->max('occurred_at'),
            'previous' => $this->totals($store, $from->copy()->subDays($days), $from),
        ];
    }

    /**
     * Headline totals for a window (used for "vs previous period" trends).
     */
    public function totals(Store $store, Carbon $from, Carbon $to): array
    {
        $rows = AnalyticsEvent::where('store_id', $store->id)->whereBetween('occurred_at', [$from, $to])->whereIn('event', ['session', 'order', 'attributed'])
            ->selectRaw('event, count(*) as n, coalesce(sum(value), 0) as total, count(distinct order_ref) as refs')->groupBy('event')->get()->keyBy('event');
        $sessions = (int) ($rows['session']->n ?? 0);
        $orders = (int) ($rows['order']->n ?? 0);
        $revenue = (float) ($rows['order']->total ?? 0);

        return [
            'sessions' => $sessions,
            'orders' => $orders,
            'revenue' => $revenue,
            'aov' => $orders ? $revenue / $orders : null,
            'conversion' => $sessions ? $orders / $sessions * 100 : null,
            'influenced_revenue' => (float) ($rows['attributed']->total ?? 0),
            'influenced_orders' => (int) ($rows['attributed']->refs ?? 0),
        ];
    }

    /**
     * % change from the previous period, or null when there's nothing to compare with.
     */
    public static function trend(?float $now, ?float $before): ?float
    {
        if ($now === null || $before === null || $before == 0.0) {
            return null;
        }

        return ($now - $before) / $before * 100;
    }

    /**
     * Per-experience numbers keyed by experience handle, aggregated in the database.
     *
     * @return array<string, array{views: int, clicks: int, adds: int, unlocks: int, accepts: int, declines: int, orders: int, revenue: float}>
     */
    private function aggregate($query): array
    {
        $rows = $query->whereNotNull('experience_handle')->whereIn('event', ['view', 'click', 'add', 'unlock', 'accept', 'decline', 'attributed'])
            ->selectRaw('experience_handle, event, count(*) as n, coalesce(sum(value), 0) as total')->groupBy('experience_handle', 'event')->get();
        $out = [];
        foreach ($rows->groupBy('experience_handle') as $handle => $events) {
            $n = fn ($e) => (int) ($events->firstWhere('event', $e)->n ?? 0);
            $out[$handle] = [
                'views' => $n('view'), 'clicks' => $n('click'), 'adds' => $n('add'), 'unlocks' => $n('unlock'),
                'accepts' => $n('accept'), 'declines' => $n('decline'), 'orders' => $n('attributed'),
                'revenue' => (float) ($events->firstWhere('event', 'attributed')->total ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Numbers for the given experiences over the last $days days.
     */
    public function forExperiences(Store $store, array $handles, int $days = 30): array
    {
        return $this->aggregate(AnalyticsEvent::where('store_id', $store->id)->whereIn('experience_handle', $handles)->where('occurred_at', '>=', now()->subDays($days)));
    }

    private function daily(Store $store, Carbon $from, int $days): array
    {
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $series[$from->copy()->addDays($i + 1)->toDateString()] = ['revenue' => 0.0, 'influenced' => 0.0];
        }
        $rows = AnalyticsEvent::where('store_id', $store->id)->where('occurred_at', '>=', $from)->whereIn('event', ['order', 'attributed'])
            ->selectRaw('date(occurred_at) as day, event, coalesce(sum(value), 0) as total')->groupBy('day', 'event')->get();
        foreach ($rows as $r) {
            $day = substr((string) $r->day, 0, 10);
            if (isset($series[$day])) {
                $series[$day][$r->event === 'order' ? 'revenue' : 'influenced'] += (float) $r->total;
            }
        }

        return $series;
    }
}
