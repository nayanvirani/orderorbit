<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Event Explorer: every event with its count, unique visitors, sessions and trend, and a
 * breakdown of one event by a property (experience, template, product, device, market, UTM).
 */
class Explorer
{
    private function scope(Store $store, Carbon $from, Carbon $to): Builder
    {
        return AnalyticsEvent::where('store_id', $store->id)->whereBetween('occurred_at', [$from, $to])
            ->whereNotNull('name')->where('event', '!=', 'attributed');
    }

    /** @return list<array{name: string, label: string, total: int, visitors: int, sessions: int, previous: int, daily: array<string, int>}> */
    public function events(Store $store, Carbon $from, Carbon $to): array
    {
        $rows = $this->scope($store, $from, $to)
            ->selectRaw('name, count(*) as total, count(distinct visitor_id) as visitors, count(distinct session_id) as sessions')
            ->groupBy('name')->get();
        $span = $from->diffInSeconds($to, true);
        $previous = $this->scope($store, $from->copy()->subSeconds($span), $from)
            ->selectRaw('name, count(*) as total')->groupBy('name')->pluck('total', 'name');
        $daily = $this->scope($store, $from, $to)->selectRaw('name, date(occurred_at) as day, count(*) as total')
            ->groupBy('name', 'day')->get()->groupBy('name');

        $days = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $days[$d->toDateString()] = 0;
        }

        return $rows->map(fn ($r) => [
            'name' => $r->name,
            'label' => Events::label($r->name),
            'total' => (int) $r->total,
            'visitors' => (int) $r->visitors,
            'sessions' => (int) $r->sessions,
            'previous' => (int) ($previous[$r->name] ?? 0),
            'daily' => array_merge($days, collect($daily[$r->name] ?? [])->mapWithKeys(fn ($x) => [substr((string) $x->day, 0, 10) => (int) $x->total])->only(array_keys($days))->all()),
        ])->sortByDesc('total')->values()->all();
    }

    /** @return list<array{value: ?string, label: ?string, total: int, visitors: int}> */
    public function breakdown(Store $store, string $name, string $dimension, Carbon $from, Carbon $to): array
    {
        if (! isset(Events::DIMENSIONS[$dimension])) {
            return [];
        }

        return $this->scope($store, $from, $to)->where('name', $name)
            ->selectRaw("{$dimension} as dim, max(label) as label, count(*) as total, count(distinct visitor_id) as visitors")
            ->groupBy($dimension)->orderByDesc('total')->limit(50)->get()
            ->map(fn ($r) => ['value' => $r->dim === null ? null : (string) $r->dim, 'label' => $r->label, 'total' => (int) $r->total, 'visitors' => (int) $r->visitors])->all();
    }
}
