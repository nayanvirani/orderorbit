<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reporting over the web pixel's events: store totals (sessions, orders, revenue,
 * AOV, conversion) and, per experience, views, clicks, adds to cart, orders and
 * the revenue from the lines it added.
 */
class Analytics
{
    public function summary(Store $store, int $days = 30): array
    {
        $from = now()->subDays($days)->startOfDay();
        $events = AnalyticsEvent::where('store_id', $store->id)->where('occurred_at', '>=', $from)->get();

        $orders = $events->where('event', 'order');
        $attributed = $events->where('event', 'attributed');
        $influencedRefs = $attributed->pluck('order_ref')->unique();
        $sessions = $events->where('event', 'session')->count();
        $revenue = (float) $orders->sum('value');
        $influenced = $orders->whereIn('order_ref', $influencedRefs);

        return [
            'days' => $days,
            'sessions' => $sessions,
            'orders' => $orders->count(),
            'revenue' => $revenue,
            'aov' => $orders->count() ? $revenue / $orders->count() : null,
            'conversion' => $sessions ? $orders->count() / $sessions * 100 : null,
            'influenced_orders' => $influenced->count(),
            'influenced_revenue' => (float) $attributed->sum('value'),
            'influenced_aov' => $influenced->count() ? (float) $influenced->sum('value') / $influenced->count() : null,
            'currency' => $orders->first()?->currency ?? $store->currency,
            'daily' => $this->daily($orders, $attributed, $from, $days),
            'experiences' => $this->byExperience($events),
            'last_event_at' => AnalyticsEvent::where('store_id', $store->id)->max('occurred_at'),
        ];
    }

    /**
     * Per-experience numbers keyed by experience handle.
     *
     * @return array<string, array{views: int, clicks: int, adds: int, orders: int, revenue: float}>
     */
    public function byExperience(Collection $events): array
    {
        return $events->whereNotNull('experience_handle')->groupBy('experience_handle')->map(fn (Collection $rows) => [
            'views' => $rows->where('event', 'view')->count(),
            'clicks' => $rows->where('event', 'click')->count(),
            'adds' => $rows->where('event', 'add')->count(),
            'orders' => $rows->where('event', 'attributed')->count(),
            'revenue' => (float) $rows->where('event', 'attributed')->sum('value'),
        ])->all();
    }

    /**
     * Numbers for the given experiences over the last $days days.
     */
    public function forExperiences(Store $store, array $handles, int $days = 30): array
    {
        return $this->byExperience(AnalyticsEvent::where('store_id', $store->id)
            ->whereIn('experience_handle', $handles)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->get());
    }

    private function daily(Collection $orders, Collection $attributed, Carbon $from, int $days): array
    {
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i + 1)->toDateString();
            $series[$day] = ['revenue' => 0.0, 'influenced' => 0.0];
        }
        foreach ($orders as $o) {
            $day = $o->occurred_at->toDateString();
            if (isset($series[$day])) {
                $series[$day]['revenue'] += $o->value;
            }
        }
        foreach ($attributed as $a) {
            $day = $a->occurred_at->toDateString();
            if (isset($series[$day])) {
                $series[$day]['influenced'] += $a->value;
            }
        }

        return $series;
    }
}
