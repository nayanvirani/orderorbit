<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsFunnel;
use App\Models\Store;
use App\Services\Analytics\Attribution;
use App\Services\Analytics\Events;
use App\Services\Analytics\Explorer;
use App\Services\Analytics\Funnels;
use App\Services\Analytics\Journeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Experiences\Registry;
use App\Models\AnalyticsEvent;
use App\Services\Analytics\Analytics;
use App\Support\Spa\Page;

/**
 * Analytics (Phase 8): Event Explorer, Funnels, Revenue & Attribution and Customer Journey.
 * plans that include them; other plans see what each report does and how to get it.
 */
class AnalyticsReportsController extends Controller
{
    public function events(Request $request, Store $store, Explorer $explorer): Page|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $explorer->filter((array) $request->query('f', []));
        [$from, $to, $days] = $this->range($request);
        $name = $request->query('event');
        $dimension = array_key_exists((string) $request->query('by'), Events::DIMENSIONS) ? $request->query('by') : 'experience_handle';
        $locked = ! $store->planIncludes('advanced_analytics');

        $events = $locked ? [] : $explorer->events($store, $from, $to);
        if (! $locked && $request->query('export') === 'csv') {
            return $this->csv('events', ['Event', 'Name', 'Events', 'Unique visitors', 'Sessions', 'Previous period'],
                array_map(fn ($e) => [$e['label'], $e['name'], $e['total'], $e['visitors'], $e['sessions'], $e['previous']], $events));
        }

        $selected = $name && isset(Events::all()[$name]) ? $name : null;

        return page('analytics/events', self::sharedProps($store, $days) + [
            'events' => collect($events)->map(fn ($e) => array_merge($e, ['trend' => Analytics::trend((float) $e['total'], $e['previous'] ? (float) $e['previous'] : null), 'daily' => array_values($e['daily'] ?? [])]))->keyBy('name'),
            'catalogue' => ['Shopify storefront events' => Events::STANDARD, 'OrderOrbit Space events' => Events::ORDERORBIT],
            'dimensions' => Events::DIMENSIONS,
            'pageTypes' => Events::PAGE_TYPES,
            'filters' => (object) array_filter((array) $request->query('f', []), fn ($v) => is_string($v) && $v !== ''),
            'selected' => $selected,
            'selectedLabel' => $selected ? Events::label($selected) : null,
            'dimension' => $dimension,
            'breakdown' => ! $locked && $selected ? $explorer->breakdown($store, $selected, $dimension, $from, $to) : [],
        ]);
    }

    public function funnels(Request $request, Store $store): Page
    {
        return page('analytics/funnels', self::sharedProps($store, $this->range($request)[2], 'funnels_attribution') + self::funnelForm() + [
            'funnels' => AnalyticsFunnel::where('store_id', $store->id)->orderBy('name')->get()->map(fn ($f) => [
                'id' => $f->id, 'name' => $f->name, 'window' => Funnels::WINDOWS[$f->within] ?? $f->within,
                'steps' => collect($f->steps)->map(fn ($s) => Events::label($s['event']))->implode(' → '),
            ]),
            'presets' => collect(Funnels::PRESETS)->map(fn ($p, $key) => ['key' => $key, 'name' => $p['name'], 'steps' => collect($p['steps'])->map(fn ($e) => Events::label($e))->implode(' → ')])->values(),
        ]);
    }

    public function funnel(Request $request, Store $store, int $funnel, Funnels $funnels): Page
    {
        $funnel = AnalyticsFunnel::where('store_id', $store->id)->findOrFail($funnel);
        [$from, $to, $days] = $this->range($request);
        $compare = in_array($request->query('compare'), ['device', 'source', 'period'], true) ? $request->query('compare') : null;
        $locked = ! $store->planIncludes('funnels_attribution');
        $report = $locked ? null : $funnels->report($store, $funnel->steps, $funnel->within, $from, $to, $compare === 'period' ? null : $compare);
        $previous = ! $locked && $compare === 'period'
            ? $funnels->report($store, $funnel->steps, $funnel->within, $from->copy()->subDays($days), $from)
            : null;

        if ($report) {
            $report['steps'] = array_map(fn ($s) => $s + ['median' => Funnels::human($s['median_seconds'] ?? null)], $report['steps']);
        }

        return page('analytics/funnel', self::sharedProps($store, $days, 'funnels_attribution') + self::funnelForm() + [
            'funnel' => ['id' => $funnel->id, 'name' => $funnel->name, 'within' => $funnel->within, 'window' => Funnels::WINDOWS[$funnel->within] ?? '', 'steps' => array_values($funnel->steps)],
            'report' => $report, 'previous' => $previous, 'compare' => $compare,
            'compareOptions' => Funnels::COMPARE, 'maxEvents' => Funnels::MAX_EVENTS,
        ]);
    }

    public function storeFunnel(Request $request, Store $store): RedirectResponse
    {
        if ($preset = Funnels::PRESETS[$request->input('preset')] ?? null) {
            $funnel = AnalyticsFunnel::create(['store_id' => $store->id, 'name' => $preset['name'], 'steps' => array_map(fn ($e) => ['event' => $e], $preset['steps']), 'within' => '7d']);

            return redirect()->to(app_route('app.analytics.funnel', ['funnel' => $funnel->id, 'notice' => 'saved']));
        }
        [$data, $error] = $this->validateFunnel($request);
        if ($error) {
            return redirect()->to(app_route('app.analytics.funnels', ['error' => $error]));
        }
        $funnel = AnalyticsFunnel::create(['store_id' => $store->id] + $data);

        return redirect()->to(app_route('app.analytics.funnel', ['funnel' => $funnel->id, 'notice' => 'saved']));
    }

    public function updateFunnel(Request $request, Store $store, int $funnel): RedirectResponse
    {
        $funnel = AnalyticsFunnel::where('store_id', $store->id)->findOrFail($funnel);
        [$data, $error] = $this->validateFunnel($request);
        if ($error) {
            return redirect()->to(app_route('app.analytics.funnel', ['funnel' => $funnel->id, 'error' => $error]));
        }
        $funnel->update($data);

        return redirect()->to(app_route('app.analytics.funnel', ['funnel' => $funnel->id, 'notice' => 'saved']));
    }

    public function destroyFunnel(Request $request, Store $store, int $funnel): RedirectResponse
    {
        AnalyticsFunnel::where('store_id', $store->id)->findOrFail($funnel)->delete();

        return redirect()->to(app_route('app.analytics.funnels', ['notice' => 'deleted']));
    }

    public function revenue(Request $request, Store $store, Attribution $attribution): Page|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        [$from, $to, $days] = $this->range($request);
        $window = array_key_exists((int) $request->query('window'), Attribution::WINDOWS) ? (int) $request->query('window') : 7;
        $model = array_key_exists((string) $request->query('model'), Attribution::MODELS) ? $request->query('model') : 'last';
        $experiences = $store->experiences()->with('publishedVersion')->get()->keyBy('handle');
        $templates = $experiences->map(fn ($e) => $e->publishedVersion?->template_key)->filter()->all();
        $locked = ! $store->planIncludes('funnels_attribution');

        $report = $locked ? null : $attribution->report($store, $from, $to, $window, $model, $templates);
        if ($report && $request->query('export') === 'csv') {
            return $this->csv('revenue', ['Group', 'Name', 'Orders', 'Revenue'], array_merge(
                array_map(fn ($k, $r) => ['Traffic source ('.Attribution::MODELS[$model].')', $k, $r['orders'], round($r['revenue'], 2)], array_keys($report['by_source']), $report['by_source']),
                array_map(fn ($k, $r) => ['UTM campaign', $k, $r['orders'], round($r['revenue'], 2)], array_keys($report['by_campaign']), $report['by_campaign']),
                array_map(fn ($k, $r) => ['Experience (direct)', $experiences[$k]->name ?? $k, $r['direct_orders'], round($r['direct_revenue'], 2)], array_keys($report['by_experience']), $report['by_experience']),
            ));
        }

        $templateNames = collect($templates)->mapWithKeys(function ($key, $handle) use ($experiences) {
            $e = $experiences[$handle];

            return [$key => Registry::has($e->type) ? (Registry::template($e->type, $key)['name'] ?? $key) : $key];
        });

        return page('analytics/revenue', self::sharedProps($store, $days, 'funnels_attribution') + [
            'report' => $report,
            'window' => $window,
            'model' => $model,
            'models' => Attribution::MODELS,
            'windows' => Attribution::WINDOWS,
            'templateNames' => $templateNames,
            // Revenue per A/B test variant, for tests that ran in this period.
            'tests' => $locked ? [] : \App\Models\Experiments\Experiment::with(['experience', 'variants'])->where('store_id', $store->id)->whereNotNull('started_at')
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $from))->latest('started_at')->limit(10)->get()
                ->map(function ($x) {
                    $results = app(\App\Services\Experiments\Results::class)->for($x);

                    return ['id' => $x->id, 'name' => $x->name, 'variants' => $x->variants->map(fn ($v) => ['key' => $v->key, 'name' => $v->name, 'winner' => $results['decision']['winner'] === $v->key]
                        + array_intersect_key($results['variants'][$v->key], array_flip(['visitors', 'orders', 'revenue', 'revenue_per_visitor'])))];
                }),
        ]);
    }

    public function journeys(Request $request, Store $store, Journeys $journeys): Page
    {
        [$from, $to, $days] = $this->range($request);
        $buyers = $request->query('all') !== '1';
        $locked = ! $store->planIncludes('customer_journeys');

        $people = $locked ? null : $journeys->list($store, $from, $to, $buyers);
        $people?->setCollection($people->getCollection()->map(fn ($p) => [
            'visitor_id' => $p->visitor_id, 'name' => $p->customer_id ? 'Customer '.$p->customer_id : 'Visitor '.substr($p->visitor_id, 0, 8),
            'first_seen' => Carbon::parse($p->first_seen), 'last_seen' => Carbon::parse($p->last_seen), 'sessions' => (int) $p->sessions,
            'orders' => (int) $p->orders, 'revenue' => (float) $p->revenue, 'source' => $p->source, 'repeat' => $p->total_orders > 1,
        ]));

        return page('analytics/journeys', self::sharedProps($store, $days, 'customer_journeys') + ['people' => $people, 'buyers' => $buyers]);
    }

    public function journey(Request $request, Store $store, string $visitor, Journeys $journeys): Page
    {
        abort_unless($store->planIncludes('customer_journeys'), 404);
        $journey = $journeys->show($store, $visitor);
        abort_if($journey['sessions'] === [], 404);

        return page('analytics/journey', self::sharedProps($store, 30, 'customer_journeys') + [
            'visitor' => $visitor,
            'journey' => $journey,
            'pageTypes' => Events::PAGE_TYPES,
        ]);
    }

    private function csv(string $name, array $header, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "orderorbit-{$name}-".now()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: array, 1: ?string} */
    private function validateFunnel(Request $request): array
    {
        $name = mb_substr(trim(strip_tags((string) $request->input('name'))), 0, 80);
        $steps = collect((array) $request->input('steps', []))
            ->map(fn ($s) => is_array($s) ? $s : ['event' => $s])
            ->filter(fn ($s) => isset(Events::all()[$s['event'] ?? '']))
            ->map(fn ($s) => array_filter(['event' => $s['event'], 'experience' => preg_match('/^[A-Za-z0-9_\-]{1,64}$/', (string) ($s['experience'] ?? '')) ? $s['experience'] : null]))
            ->values()->take(8)->all();
        $within = array_key_exists((string) $request->input('within'), Funnels::WINDOWS) ? $request->input('within') : '7d';

        if ($name === '') {
            return [[], 'Give the funnel a name.'];
        }
        if (count($steps) < 2) {
            return [[], 'A funnel needs at least two steps.'];
        }

        return [['name' => $name, 'steps' => $steps, 'within' => $within], null];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: int} */
    private function range(Request $request): array
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;

        return [now()->subDays($days)->startOfDay(), now(), $days];
    }

    /** What every analytics page needs: range, plan lock, data freshness and experience names. */
    public static function sharedProps(Store $store, int $days, string $feature = 'advanced_analytics'): array
    {
        $freshest = AnalyticsEvent::where('store_id', $store->id)->max('occurred_at');

        return [
            'days' => $days, 'locked' => ! $store->planIncludes($feature), 'lockedFeature' => $feature,
            'freshest' => $freshest ? Carbon::parse($freshest) : null,
            'docsUrl' => route('site.docs', 'analytics'),
            'experiences' => self::experienceMap($store),
            'currency' => $store->currency ?? 'USD',
            'error' => request()->query('error'),
        ];
    }

    /** Experiences by handle: id, name and type label. */
    public static function experienceMap(Store $store): array
    {
        return $store->experiences()->get()->mapWithKeys(fn ($e) => [$e->handle => [
            'id' => $e->id, 'name' => $e->name, 'type' => Registry::has($e->type) ? Registry::type($e->type)['label'] : $e->type, 'archived' => $e->status === 'archived',
        ]])->all();
    }

    /** Choices for the funnel editor. */
    private static function funnelForm(): array
    {
        $standard = Events::STANDARD;
        unset($standard['session_started']);

        return ['eventGroups' => ['Storefront' => $standard, 'OrderOrbit Space' => Events::ORDERORBIT], 'windows' => Funnels::WINDOWS];
    }
}
