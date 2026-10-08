<?php

namespace App\Http\Controllers\App\Api;

use App\Experiences\Registry;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Audiences\PersonalizationRule;
use App\Models\Automation\Workflow;
use App\Models\Automation\WorkflowRun;
use App\Models\Experiments\Experiment;
use App\Models\Store;
use App\Services\Analytics\Analytics;
use App\Services\Analytics\PixelConnector;
use App\Services\Experiments\Results;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Home's cards, each its own endpoint so the page shows at once and fills in as data arrives.
 */
class DashboardApiController extends Controller
{
    /** Setup progress, health alerts and recommended next steps. */
    public function overview(Store $store): JsonResponse
    {
        $first = $store->experiences()->oldest('id')->first();
        $steps = [
            ['label' => 'Connect your store', 'done' => $store->isInstalled() && $store->missingScopes() === [], 'help' => 'Approve the permissions Growvia needs.', 'action' => 'Review connection', 'href' => '/app/settings/store'],
            ['label' => 'Choose your goal', 'done' => $store->goal !== null, 'help' => 'We recommend where to start based on it.', 'action' => 'Choose a goal', 'href' => '/app/onboarding?step=2'],
            ['label' => 'Create your first widget', 'done' => (bool) $first, 'help' => 'A bundle, gift bar, countdown or trust badge.', 'action' => 'Create one', 'href' => '/app/onboarding?step=3'],
            ['label' => 'Publish and place it', 'done' => $first && $first->status === 'published' && in_array($first->placement_status, ['placed', 'external'], true), 'help' => 'Publish, then add its block in the Theme Editor.', 'action' => 'Publish and place', 'href' => '/app/onboarding?step=7'],
            ['label' => 'Check analytics', 'done' => AnalyticsEvent::where('store_id', $store->id)->exists(), 'help' => 'Visit your store and the first numbers appear within minutes.', 'action' => 'Check analytics', 'href' => '/app/onboarding?step=8'],
        ];
        $next = collect($steps)->firstWhere('done', false);

        // The analytics pixel connects on install; this covers stores installed before it existed.
        $pixel = app(PixelConnector::class);
        $pixel->ensure($store);
        app(\App\Services\Experiences\PlacementDetector::class)->refreshIfStale($store);

        $alerts = [];
        if ($store->missingScopes() !== []) {
            $alerts[] = ['tone' => 'critical', 'text' => 'Your Shopify connection needs attention.', 'action' => 'Review connection', 'href' => '/app/settings/store'];
        }
        if (! $pixel->connected($store->fresh())) {
            $alerts[] = ['tone' => 'warning', 'text' => 'Analytics isn\'t connected, so visits, orders and tests aren\'t measured.', 'action' => 'Connect analytics', 'href' => '/app/analytics'];
        }
        if ($failed = WorkflowRun::where('store_id', $store->id)->where('status', 'failed')->where('test', false)->where('created_at', '>=', now()->subDays(7))->count()) {
            $alerts[] = ['tone' => 'warning', 'text' => "{$failed} workflow ".str('run')->plural($failed).' failed this week.', 'action' => 'See why', 'href' => '/app/automation/runs?status=failed'];
        }
        if ($unplaced = $store->experiences()->where('status', 'published')->where('placement_status', 'not_placed')->count()) {
            $alerts[] = ['tone' => 'warning', 'text' => "{$unplaced} live ".str('experience')->plural($unplaced).' '.($unplaced === 1 ? 'isn\'t' : 'aren\'t').' in your theme yet, so shoppers can\'t see '.($unplaced === 1 ? 'it' : 'them').'.', 'action' => 'Fix placement', 'href' => '/app/cro/experiences'];
        }

        $nextActions = array_slice(array_values(array_filter([
            ! $store->experiences()->whereIn('type', ['bundles', 'quantity-breaks'])->exists() ? ['text' => 'Bundles on your best seller lift units per order.', 'action' => 'Create a bundle', 'href' => route('app.bundles.types', [], false)] : null,
            ! $store->experiences()->where('type', 'progressive-gifts')->exists() ? ['text' => 'A gift or free-shipping goal just above your average order.', 'action' => 'Add a gift goal', 'href' => route('app.gifts.models', [], false)] : null,
            $store->planIncludes('ab_testing') && $store->experiences()->where('status', 'published')->exists() && ! Experiment::where('store_id', $store->id)->exists() ? ['text' => 'Find out which layout or headline earns more.', 'action' => 'Start an A/B test', 'href' => '/app/experiments'] : null,
            $store->planIncludes('automation') && ! Workflow::where('store_id', $store->id)->where('status', 'enabled')->exists() ? ['text' => 'Review requests and win-backs, sent for you.', 'action' => 'Turn on a workflow', 'href' => '/app/automation/templates'] : null,
            $store->planIncludes('personalization') && ! PersonalizationRule::where('store_id', $store->id)->exists() ? ['text' => 'Show returning customers a different offer.', 'action' => 'Personalize', 'href' => '/app/audiences/rules'] : null,
        ])), 0, 4);

        return response()->json([
            'setup' => ['done' => ! $next, 'completed' => collect($steps)->where('done', true)->count(), 'total' => count($steps), 'next' => $next, 'steps' => array_map(fn ($s) => ['label' => $s['label'], 'done' => (bool) $s['done']], $steps)],
            'alerts' => $alerts,
            'next' => $nextActions,
        ]);
    }

    /** The four headline numbers, with trends and daily series. */
    public function kpis(Request $request, Store $store, Analytics $analytics): JsonResponse
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $s = $analytics->summary($store, $days);
        $p = $s['previous'];
        $trend = fn ($a, $b) => Analytics::trend($a, $b);

        return response()->json([
            'days' => $days, 'currency' => $s['currency'],
            'revenue' => $s['revenue'], 'orders' => $s['orders'], 'aov' => $s['aov'], 'conversion' => $s['conversion'], 'sessions' => $s['sessions'],
            'influenced_revenue' => $s['influenced_revenue'], 'influenced_aov' => $s['influenced_aov'],
            'trends' => [
                'revenue' => $trend($s['revenue'], $p['revenue']), 'aov' => $trend($s['aov'], $p['aov']), 'conversion' => $trend($s['conversion'], $p['conversion']),
                'influenced_revenue' => $trend($s['influenced_revenue'], $p['influenced_revenue']),
            ],
            'daily' => ['revenue' => array_values(array_column($s['daily'], 'revenue')), 'influenced' => array_values(array_column($s['daily'], 'influenced'))],
            'has_events' => (bool) $s['last_event_at'],
        ]);
    }

    /** Best experiences by revenue, then views. */
    public function top(Request $request, Store $store, Analytics $analytics): JsonResponse
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $experiences = $store->experiences()->where('status', '!=', 'archived')->get()->keyBy('handle');
        $stats = $analytics->forExperiences($store, $experiences->keys()->all(), $days);
        $items = collect($stats)->map(fn ($r, $handle) => [
            'id' => $experiences[$handle]->id, 'name' => $experiences[$handle]->name, 'href' => route('app.cro.experiences.show', $experiences[$handle], false),
            'type' => Registry::has($experiences[$handle]->type) ? Registry::type($experiences[$handle]->type)['label'] : $experiences[$handle]->type,
            'views' => $r['views'], 'orders' => $r['orders'], 'revenue' => round($r['revenue'], 2),
            'conversion' => $r['views'] ? round($r['orders'] / $r['views'] * 100, 1) : 0,
        ])->sortByDesc(fn ($r) => [$r['revenue'], $r['views']])->take(5)->values();

        return response()->json(['currency' => $store->currency ?? 'USD', 'items' => $items]);
    }

    /** Feature status, automation health and running tests. */
    public function activity(Store $store, Results $results): JsonResponse
    {
        $byType = $store->experiences()->where('status', '!=', 'archived')->selectRaw('type, status, count(*) as total')->groupBy('type', 'status')->get();
        $features = collect(Registry::features())->map(fn ($f, $key) => [
            'key' => $key, 'label' => $f['label'],
            'href' => isset($f['module']) ? route($f['module'], [], false) : route('app.features.show', ['feature' => $key], false),
            'live' => (int) $byType->whereIn('type', $f['types'])->where('status', 'published')->sum('total'),
            'total' => (int) $byType->whereIn('type', $f['types'])->sum('total'),
        ])->values();
        $runs = WorkflowRun::where('store_id', $store->id)->where('test', false)->where('created_at', '>=', now()->subDays(30))->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $finished = (int) ($runs['completed'] ?? 0) + (int) ($runs['failed'] ?? 0);

        return response()->json([
            'features' => $features,
            'automation' => [
                'available' => $store->planIncludes('automation') || $runs->sum() > 0,
                'workflows' => Workflow::where('store_id', $store->id)->where('status', 'enabled')->count(),
                'runs' => (int) $runs->sum(), 'failed' => (int) ($runs['failed'] ?? 0),
                'success_rate' => $finished ? round(($runs['completed'] ?? 0) / $finished * 100, 1) : null,
            ],
            'tests' => Experiment::with(['experience', 'variants'])->where('store_id', $store->id)->where('status', 'running')->latest('started_at')->limit(3)->get()
                ->map(function ($x) use ($results) {
                    $r = $results->for($x);

                    return ['id' => $x->id, 'name' => $x->name, 'href' => route('app.experiments.show', $x, false), 'progress' => round($r['decision']['progress'] * 100), 'headline' => $r['decision']['headline']];
                }),
        ]);
    }
}
