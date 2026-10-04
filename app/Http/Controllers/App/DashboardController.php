<?php

namespace App\Http\Controllers\App;

use App\Experiences\Registry;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(\Illuminate\Http\Request $request, Store $store): View
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $checklist = [
            ['label' => 'Connect your store', 'done' => $store->isInstalled() && $store->missingScopes() === [], 'route' => 'app.settings.store'],
            ['label' => 'Choose your goal', 'done' => $store->goal !== null, 'route' => 'app.onboarding'],
            ['label' => 'Choose a plan', 'done' => $store->hasPlanAccess(), 'route' => 'app.settings.billing'],
            ['label' => 'Create your first experience', 'done' => $store->experiences()->exists(), 'route' => 'app.cro.experiences.create'],
            ['label' => 'Place it in the Theme Editor', 'done' => $store->experiences()->where('placement_status', 'placed')->exists(), 'route' => 'app.cro.experiences.index'],
            ['label' => 'Verify analytics', 'done' => \App\Models\AnalyticsEvent::where('store_id', $store->id)->exists(), 'route' => 'app.analytics'],
        ];

        $alerts = [];
        if ($store->missingScopes() !== []) {
            $alerts[] = ['tone' => 'critical', 'text' => 'Your Shopify connection needs attention. Open Settings → Store to review it.', 'route' => 'app.settings.store', 'action' => 'Review connection'];
        }
        if ($store->capability('online_store_2') === false) {
            $alerts[] = ['tone' => 'warning', 'text' => 'Your current theme doesn\'t support app blocks. OrderOrbit storefront blocks need an Online Store 2.0 theme.', 'route' => 'app.settings.store', 'action' => 'View details'];
        }

        $byType = $store->experiences()->where('status', '!=', 'archived')
            ->selectRaw('type, status, count(*) as total')->groupBy('type', 'status')->get();
        $features = collect(Registry::features())->map(fn ($feature) => $feature + [
            'description' => Registry::type($feature['types'][0])['description'],
            'live' => (int) $byType->whereIn('type', $feature['types'])->where('status', 'published')->sum('total'),
            'total' => (int) $byType->whereIn('type', $feature['types'])->sum('total'),
        ])->all();

        // The analytics pixel connects on install; this covers stores installed before it existed.
        $pixel = app(\App\Services\Analytics\PixelConnector::class);
        $pixel->ensure($store);
        $summary = app(\App\Services\Analytics\Analytics::class)->summary($store, $days);

        // Health: the pixel, failed workflow runs and experiences that aren't live yet.
        if (! $pixel->connected($store->fresh())) {
            $alerts[] = ['tone' => 'warning', 'text' => 'Analytics isn\'t connected, so visits, orders and A/B tests aren\'t being measured.', 'route' => 'app.analytics', 'action' => 'Connect analytics'];
        }
        $failedRuns = \App\Models\Automation\WorkflowRun::where('store_id', $store->id)->where('status', 'failed')->where('test', false)->where('created_at', '>=', now()->subDays(7))->count();
        if ($failedRuns) {
            $alerts[] = ['tone' => 'warning', 'text' => $failedRuns.' workflow '.\Illuminate\Support\Str::plural('run', $failedRuns).' failed in the last 7 days.', 'route' => 'app.automation.runs', 'params' => ['status' => 'failed'], 'action' => 'View failed runs'];
        }
        $unpublished = $store->experiences()->where('status', 'published')->where('has_unpublished_changes', true)->count();
        if ($unpublished) {
            $alerts[] = ['tone' => 'info', 'text' => $unpublished.' live '.\Illuminate\Support\Str::plural('experience', $unpublished).' '.($unpublished === 1 ? 'has' : 'have').' changes that aren\'t published yet.', 'route' => 'app.cro.experiences.index', 'action' => 'Review experiences'];
        }
        $unplaced = $store->experiences()->where('status', 'published')->where('placement_status', 'not_placed')->count();
        if ($unplaced) {
            $alerts[] = ['tone' => 'warning', 'text' => $unplaced.' published '.\Illuminate\Support\Str::plural('experience', $unplaced).' '.($unplaced === 1 ? 'isn\'t' : 'aren\'t').' placed in your theme, so shoppers can\'t see '.($unplaced === 1 ? 'it' : 'them').'.', 'route' => 'app.cro.experiences.index', 'action' => 'Fix placement'];
        }

        // Top experiences by revenue, then views.
        $experiences = $store->experiences()->get()->keyBy('handle');
        $top = collect($summary['experiences'])->map(fn ($r, $handle) => $r + ['experience' => $experiences[$handle] ?? null])
            ->filter(fn ($r) => $r['experience'])->sortByDesc(fn ($r) => [$r['revenue'], $r['views']])->take(5);

        // Automation health (last 30 days) and running A/B tests.
        $runs = \App\Models\Automation\WorkflowRun::where('store_id', $store->id)->where('test', false)->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $automation = [
            'workflows' => \App\Models\Automation\Workflow::where('store_id', $store->id)->where('status', 'enabled')->count(),
            'runs' => (int) $runs->sum(), 'completed' => (int) ($runs['completed'] ?? 0), 'failed' => (int) ($runs['failed'] ?? 0),
            'retrying' => \App\Models\Automation\WorkflowRun::where('store_id', $store->id)->where('status', 'waiting')->where('attempts', '>', 0)->count(),
        ];
        $tests = \App\Models\Experiments\Experiment::with(['experience', 'variants'])->where('store_id', $store->id)->where('status', 'running')->latest('started_at')->limit(3)->get()
            ->map(fn ($x) => ['experiment' => $x, 'results' => app(\App\Services\Experiments\Results::class)->for($x)]);

        // Recommended next actions: the most useful things this store hasn't done yet.
        $next = array_slice(array_values(array_filter([
            ! $store->experiences()->whereIn('type', ['bundles', 'quantity-breaks'])->exists() ? ['text' => 'Create a bundle on your best-selling product to lift units per order.', 'route' => 'app.bundles.types', 'action' => 'Create a bundle'] : null,
            ! $store->experiences()->where('type', 'progressive-gifts')->exists() ? ['text' => 'Add a free-shipping or gift milestone just above your average order value.', 'route' => 'app.gifts.models', 'action' => 'Set up gifts'] : null,
            $store->planIncludes('ab_testing') && $store->experiences()->where('status', 'published')->exists() && ! \App\Models\Experiments\Experiment::where('store_id', $store->id)->exists() ? ['text' => 'Test a new layout or headline on one of your live experiences.', 'route' => 'app.experiments.index', 'action' => 'Create an A/B test'] : null,
            $store->planIncludes('automation') && ! $automation['workflows'] ? ['text' => 'Turn on a review request or win-back workflow to bring customers back.', 'route' => 'app.automation.templates', 'action' => 'Browse workflows'] : null,
            $store->planIncludes('checkout') && ! $store->experiences()->where('type', 'like', 'ty-%')->exists() ? ['text' => 'Add a cross-sell or next-order code to your Thank You page.', 'route' => 'app.features.show', 'params' => ['feature' => 'thank-you'], 'action' => 'Add a Thank You block'] : null,
            $store->planIncludes('personalization') && ! \App\Models\Audiences\PersonalizationRule::where('store_id', $store->id)->exists() ? ['text' => 'Show returning customers a different offer than first-time visitors.', 'route' => 'app.audiences.rules', 'action' => 'Create a rule'] : null,
        ])), 0, 3);

        return view('app.dashboard', [
            'store' => $store,
            'summary' => $summary,
            'days' => $days,
            'top' => $top,
            'automation' => $automation,
            'tests' => $tests,
            'next' => $next,
            'features' => $features,
            'checklist' => $checklist,
            'checklistDone' => collect($checklist)->every('done'),
            'alerts' => $alerts,
            'recent' => AuditLog::with('actor')->where('store_id', $store->id)->latest('id')->limit(5)->get(),
        ]);
    }
}
