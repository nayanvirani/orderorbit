<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Revenue & Attribution. Models are clearly labelled, and none proves causality:
 *  - last touch:  the traffic source of the session the order was placed in
 *  - first touch: the source of the visitor's first session within the attribution window
 *  - direct offer revenue: the order lines an experience added (bundles, gifts, upsells)
 *  - experience-assisted: orders from visitors who saw or used an experience within the window
 *    (each experience gets the full order, so assisted totals overlap)
 */
class Attribution
{
    public const WINDOWS = [1 => '1 day', 7 => '7 days', 30 => '30 days'];

    public const MODELS = ['last' => 'Last touch', 'first' => 'First touch'];

    /** Experience events that count as a touch for the assisted model. */
    private const TOUCHES = ['view', 'click', 'add', 'unlock', 'accept'];

    /**
     * @param  array<string, string>  $templates  experience handle => template key
     */
    public function report(Store $store, Carbon $from, Carbon $to, int $window, string $model, array $templates = []): array
    {
        $orders = AnalyticsEvent::where('store_id', $store->id)->where('event', 'order')->whereBetween('occurred_at', [$from, $to])->get();
        $attributed = AnalyticsEvent::where('store_id', $store->id)->where('event', 'attributed')->whereBetween('occurred_at', [$from, $to])->get();
        $sessions = AnalyticsEvent::where('store_id', $store->id)->where('event', 'session')->whereBetween('occurred_at', [$from, $to]);
        $sessionCount = (clone $sessions)->count();
        $visitorCount = (clone $sessions)->whereNotNull('visitor_id')->distinct()->count('visitor_id');

        $visitors = $orders->pluck('visitor_id')->filter()->unique()->values();
        $since = $from->copy()->subDays($window);
        $firstSessions = $this->byVisitor($store, $visitors, $since, $to, fn ($q) => $q->where('event', 'session'));
        $touches = $this->byVisitor($store, $visitors, $since, $to, fn ($q) => $q->whereIn('event', self::TOUCHES)->whereNotNull('experience_handle'));

        $revenue = (float) $orders->sum('value');
        $bySource = [];
        $byCampaign = [];
        $assisted = [];
        $influenced = [];

        foreach ($orders as $order) {
            $at = $order->occurred_at->getTimestamp();
            $start = $at - $window * 86400;
            $source = $order->source;
            $campaign = $order->campaign;
            if ($model === 'first' && $order->visitor_id) {
                $first = collect($firstSessions[$order->visitor_id] ?? [])->first(fn ($s) => $s['time'] >= $start && $s['time'] <= $at);
                if ($first) {
                    [$source, $campaign] = [$first['source'], $first['campaign']];
                }
            }
            $source = $source ?: 'unknown';
            $bySource[$source] = $this->add($bySource[$source] ?? null, $order->value);
            if ($campaign) {
                $byCampaign[$campaign] = $this->add($byCampaign[$campaign] ?? null, $order->value);
            }

            $touched = collect($touches[$order->visitor_id] ?? [])->filter(fn ($t) => $t['time'] >= $start && $t['time'] <= $at)->pluck('handle')->unique();
            foreach ($touched as $handle) {
                $assisted[$handle] = $this->add($assisted[$handle] ?? null, $order->value);
            }
            if ($touched->isNotEmpty()) {
                $influenced[$order->order_ref] = $order->value;
            }
        }
        foreach ($attributed->pluck('order_ref')->unique() as $ref) {
            $influenced[$ref] = (float) ($orders->firstWhere('order_ref', $ref)?->value ?? $attributed->where('order_ref', $ref)->sum('value'));
        }

        $direct = $attributed->groupBy('experience_handle')->map(fn (Collection $rows) => ['orders' => $rows->pluck('order_ref')->unique()->count(), 'revenue' => (float) $rows->sum('value')]);
        $experiences = collect(array_keys($assisted))->merge($direct->keys())->unique()->mapWithKeys(fn ($h) => [$h => [
            'direct_orders' => $direct[$h]['orders'] ?? 0, 'direct_revenue' => $direct[$h]['revenue'] ?? 0.0,
            'assisted_orders' => $assisted[$h]['orders'] ?? 0, 'assisted_revenue' => $assisted[$h]['revenue'] ?? 0.0,
            'template' => $templates[$h] ?? null,
        ]])->sortByDesc(fn ($r) => $r['direct_revenue'] + $r['assisted_revenue']);

        $byTemplate = $experiences->filter(fn ($r) => $r['template'])->groupBy('template')->map(fn (Collection $rows) => [
            'direct_revenue' => $rows->sum('direct_revenue'), 'assisted_revenue' => $rows->sum('assisted_revenue'), 'experiences' => $rows->count(),
        ])->sortByDesc('direct_revenue');

        uasort($bySource, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        uasort($byCampaign, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return [
            'orders' => $orders->count(),
            'revenue' => $revenue,
            'aov' => $orders->count() ? $revenue / $orders->count() : null,
            'sessions' => $sessionCount,
            'visitors' => $visitorCount,
            'per_session' => $sessionCount ? $revenue / $sessionCount : null,
            'per_visitor' => $visitorCount ? $revenue / $visitorCount : null,
            'influenced_orders' => count($influenced),
            'influenced_revenue' => array_sum($influenced),
            'direct_revenue' => (float) $attributed->sum('value'),
            'currency' => $orders->first()?->currency ?? $store->currency,
            'by_source' => $bySource,
            'by_campaign' => array_slice($byCampaign, 0, 20, true),
            'by_experience' => $experiences->all(),
            'by_template' => $byTemplate->all(),
        ];
    }

    private function add(?array $row, float $value): array
    {
        return ['orders' => ($row['orders'] ?? 0) + 1, 'revenue' => ($row['revenue'] ?? 0) + $value];
    }

    /** Events per visitor, oldest first. */
    private function byVisitor(Store $store, Collection $visitors, Carbon $from, Carbon $to, callable $filter): array
    {
        $out = [];
        foreach ($visitors->chunk(500) as $chunk) {
            $query = AnalyticsEvent::where('store_id', $store->id)->whereIn('visitor_id', $chunk->all())->whereBetween('occurred_at', [$from, $to]);
            $filter($query);
            foreach ($query->orderBy('occurred_at')->get(['visitor_id', 'experience_handle', 'source', 'campaign', 'occurred_at']) as $e) {
                $out[$e->visitor_id][] = ['time' => $e->occurred_at->getTimestamp(), 'handle' => $e->experience_handle, 'source' => $e->source, 'campaign' => $e->campaign];
            }
        }

        return $out;
    }
}
