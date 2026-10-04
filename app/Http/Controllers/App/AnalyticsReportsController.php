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
use Illuminate\View\View;

/**
 * Analytics (Phase 8): Event Explorer, Funnels, Revenue & Attribution and Customer Journey.
 * Growth and Scale; other plans see what each report does and how to get it.
 */
class AnalyticsReportsController extends Controller
{
    public function events(Request $request, Store $store, Explorer $explorer): View
    {
        [$from, $to, $days] = $this->range($request);
        $name = $request->query('event');
        $dimension = array_key_exists((string) $request->query('by'), Events::DIMENSIONS) ? $request->query('by') : 'experience_handle';
        $locked = ! $store->planIncludes('advanced_analytics');

        return view('app.analytics.events', $this->shared($store, $days) + [
            'events' => $locked ? [] : $explorer->events($store, $from, $to),
            'selected' => $name && isset(Events::all()[$name]) ? $name : null,
            'dimension' => $dimension,
            'breakdown' => ! $locked && $name ? $explorer->breakdown($store, $name, $dimension, $from, $to) : [],
        ]);
    }

    public function funnels(Request $request, Store $store): View
    {
        return view('app.analytics.funnels', $this->shared($store, $this->range($request)[2]) + [
            'funnels' => AnalyticsFunnel::where('store_id', $store->id)->orderBy('name')->get(),
        ]);
    }

    public function funnel(Request $request, Store $store, int $funnel, Funnels $funnels): View
    {
        $funnel = AnalyticsFunnel::where('store_id', $store->id)->findOrFail($funnel);
        [$from, $to, $days] = $this->range($request);
        $compare = in_array($request->query('compare'), ['device', 'source', 'period'], true) ? $request->query('compare') : null;
        $locked = ! $store->planIncludes('advanced_analytics');
        $report = $locked ? null : $funnels->report($store, $funnel->steps, $funnel->within, $from, $to, $compare === 'period' ? null : $compare);
        $previous = ! $locked && $compare === 'period'
            ? $funnels->report($store, $funnel->steps, $funnel->within, $from->copy()->subDays($days), $from)
            : null;

        return view('app.analytics.funnel', $this->shared($store, $days) + compact('funnel', 'report', 'previous', 'compare'));
    }

    public function storeFunnel(Request $request, Store $store): RedirectResponse|View
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

    public function revenue(Request $request, Store $store, Attribution $attribution): View
    {
        [$from, $to, $days] = $this->range($request);
        $window = array_key_exists((int) $request->query('window'), Attribution::WINDOWS) ? (int) $request->query('window') : 7;
        $model = array_key_exists((string) $request->query('model'), Attribution::MODELS) ? $request->query('model') : 'last';
        $experiences = $store->experiences()->with('publishedVersion')->get()->keyBy('handle');
        $templates = $experiences->map(fn ($e) => $e->publishedVersion?->template_key)->filter()->all();
        $locked = ! $store->planIncludes('advanced_analytics');

        return view('app.analytics.revenue', $this->shared($store, $days) + [
            'report' => $locked ? null : $attribution->report($store, $from, $to, $window, $model, $templates),
            'window' => $window,
            'model' => $model,
        ]);
    }

    public function journeys(Request $request, Store $store, Journeys $journeys): View
    {
        [$from, $to, $days] = $this->range($request);
        $buyers = $request->query('all') !== '1';
        $locked = ! $store->planIncludes('advanced_analytics');

        return view('app.analytics.journeys', $this->shared($store, $days) + [
            'people' => $locked ? null : $journeys->list($store, $from, $to, $buyers),
            'buyers' => $buyers,
        ]);
    }

    public function journey(Request $request, Store $store, string $visitor, Journeys $journeys): View
    {
        abort_unless($store->planIncludes('advanced_analytics'), 404);
        $journey = $journeys->show($store, $visitor);
        abort_if($journey['sessions'] === [], 404);

        return view('app.analytics.journey', $this->shared($store, 30) + [
            'visitor' => $visitor,
            'journey' => $journey,
        ]);
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

    private function shared(Store $store, int $days): array
    {
        return [
            'store' => $store, 'days' => $days, 'locked' => ! $store->planIncludes('advanced_analytics'),
            'experiences' => $store->experiences()->with('publishedVersion')->get()->keyBy('handle'),
        ];
    }
}
