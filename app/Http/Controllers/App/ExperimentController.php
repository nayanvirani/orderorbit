<?php

namespace App\Http\Controllers\App;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\Experiments\Experiment;
use App\Models\Store;
use App\Services\Experiments\ExperimentManager;
use App\Services\Experiments\Results;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Spa\Page;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A/B testing (Phase 9): tests list, setup, results and lifecycle.
 */
class ExperimentController extends Controller
{
    public function __construct(private readonly ExperimentManager $manager) {}

    public function index(Request $request, Store $store, Results $results): Page
    {
        $tab = in_array($request->query('tab'), ['active', 'drafts', 'completed'], true) ? $request->query('tab') : 'active';
        $statuses = ['active' => ['running', 'paused'], 'drafts' => ['draft'], 'completed' => ['completed', 'stopped']][$tab];
        $experiments = Experiment::with(['experience', 'variants'])->where('store_id', $store->id)->whereIn('status', $statuses)
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->latest('updated_at')->get();

        return page('experiments/index', [
            'tab' => $tab,
            'q' => (string) $request->query('q', ''),
            'experiments' => $experiments->map(function (Experiment $x) use ($results) {
                $s = $x->started_at ? $results->for($x) : null;

                return [
                    'id' => $x->id, 'name' => $x->name, 'status' => $x->status, 'experience' => $x->experience->name,
                    'variants' => $x->variants->map(fn ($v) => $v->key.' '.$v->allocation.'%')->implode(' · '),
                    'metric' => ExperimentManager::PRIMARY[$x->primary_metric] ?? $x->primary_metric,
                    'visitors' => $s ? collect($s['variants'])->map(fn ($m) => number_format($m['visitors']))->implode(' / ') : null,
                    'progress' => $s ? round($s['decision']['progress'] * 100) : null,
                    'days' => $x->started_at ? (int) floor($x->daysRunning()) : null,
                    'result' => $s ? match ($s['decision']['state'] ?? null) {
                        'winner' => 'Winner: '.$s['decision']['winner'], 'control' => 'Control wins', 'no_winner' => 'No clear winner', 'guardrail' => 'Guardrail breached', default => 'Running',
                    } : null,
                ];
            }),
            'counts' => (object) Experiment::where('store_id', $store->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'testable' => $this->testable($store)->map(fn ($e) => ['id' => $e->id, 'label' => $e->name.' · '.Registry::type($e->type)['label']])->values(),
            'abTesting' => $store->planIncludes('ab_testing'),
            'docsUrl' => route('site.docs', 'ab-testing'),
            'error' => $request->query('error'),
        ]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $experience = Experience::where('store_id', $store->id)->findOrFail((int) $request->input('experience_id'));
        try {
            $experiment = $this->manager->create($store, $experience, $this->user($request));
        } catch (RuntimeException $e) {
            return redirect()->to(app_route('app.experiments.index', ['tab' => 'drafts', 'error' => $e->getMessage()]));
        }

        return redirect()->to(app_route('app.experiments.edit', ['experiment' => $experiment->id, 'notice' => 'created']));
    }

    public function edit(Request $request, Store $store, int $experiment): Page|RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        if (in_array($experiment->status, ['running', 'completed', 'stopped'], true)) {
            return redirect()->to(app_route('app.experiments.show', ['experiment' => $experiment->id]));
        }

        return $this->setup($store, $experiment, []);
    }

    public function update(Request $request, Store $store, int $experiment): Page|RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        $errors = $this->manager->save($experiment, $request->all());
        $experiment = $experiment->fresh(['experience', 'variants']);

        if ($errors) {
            return $this->setup($store, $experiment, $errors, 'Saved as a draft. Fix the highlighted fields before you launch.');
        }
        if ($request->input('action') === 'launch') {
            try {
                $this->manager->launch($experiment, $this->user($request));
            } catch (RuntimeException $e) {
                return $this->setup($store, $experiment, [], $e->getMessage());
            }

            return redirect()->to(app_route('app.experiments.show', ['experiment' => $experiment->id, 'notice' => 'experiment_launched']));
        }

        return redirect()->to(app_route('app.experiments.edit', ['experiment' => $experiment->id, 'notice' => 'saved']));
    }

    public function show(Request $request, Store $store, int $experiment, Results $results): Page|RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        if ($experiment->status === 'draft') {
            return redirect()->to(app_route('app.experiments.edit', ['experiment' => $experiment->id]));
        }

        $r = $results->for($experiment);
        // p-values formatted like the export (Results::p), next to the raw numbers.
        foreach ($r['comparisons'] as $key => $metrics) {
            foreach ($metrics as $metric => $c) {
                if (is_array($c) && array_key_exists('p', $c)) {
                    $r['comparisons'][$key][$metric]['p_label'] = Results::p($c['p']);
                }
            }
        }

        return page('experiments/show', [
            'experiment' => [
                'id' => $experiment->id, 'name' => $experiment->name, 'status' => $experiment->status, 'hypothesis' => $experiment->hypothesis,
                'experience_id' => $experiment->experience_id, 'experience' => $experiment->experience->name,
                'primary_metric' => $experiment->primary_metric, 'primary_label' => ExperimentManager::PRIMARY[$experiment->primary_metric] ?? $experiment->primary_metric,
                'started_at' => $experiment->started_at, 'ends_at' => $experiment->ends_at,
                'min_days' => $experiment->min_days, 'min_visitors' => $experiment->min_visitors, 'min_conversions' => $experiment->min_conversions,
                'audience' => $experiment->audience ? collect($experiment->audience)->map(fn ($v, $k) => ucfirst(str_replace('_', ' ', $k)).': '.(is_array($v) ? collect($v)->map(fn ($x) => is_array($x) ? ($x['title'] ?? '') : $x)->implode(', ') : $v))->implode(' · ') : null,
                'secondary_metrics' => array_values($experiment->secondary_metrics ?? []),
                'guardrails' => array_values($experiment->guardrails ?? []),
                'variants' => $experiment->variants->map(fn ($v) => ['key' => $v->key, 'name' => $v->name, 'allocation' => $v->allocation, 'hidden' => (bool) $v->hidden]),
            ],
            'results' => $r,
            'primary' => Results::primaryKey($experiment),
            'labels' => ['secondary' => ExperimentManager::SECONDARY, 'guardrails' => ExperimentManager::GUARDRAILS],
            'logs' => $experiment->logs()->with('user')->limit(50)->get()->map(fn ($l) => ['id' => $l->id, 'created_at' => $l->created_at, 'message' => $l->message, 'user' => $l->user ? ($l->user->name ?? $l->user->email) : null]),
            'currency' => $store->currency ?? 'USD',
            'docsUrl' => route('site.docs', 'ab-testing'),
            'error' => $request->query('error'),
        ]);
    }

    public function action(Request $request, Store $store, int $experiment, string $action): RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        $user = $this->user($request);
        try {
            match ($action) {
                'pause' => $this->manager->pause($experiment, $user),
                'resume' => $this->manager->resume($experiment, $user),
                'stop' => $this->manager->stop($experiment, $user),
                'apply' => $this->manager->applyWinner($experiment, (string) $request->input('variant'), $user),
            };
        } catch (RuntimeException $e) {
            return redirect()->to(app_route('app.experiments.show', ['experiment' => $experiment->id, 'error' => $e->getMessage()]));
        }

        return redirect()->to(app_route('app.experiments.show', ['experiment' => $experiment->id, 'notice' => 'experiment_'.$action]));
    }

    /** Version B or C of a priced offer test, in the bundle editor (G39). */
    public function version(Request $request, Store $store, int $experiment, string $key): Page|RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        abort_unless(ExperimentManager::priced($experiment->experience) && in_array($key, ['B', 'C'], true), 404);
        $experience = $experiment->experience;
        $config = $experiment->variants->firstWhere('key', $key)?->config
            ?? \App\Experiences\BundleSchema::normalize($experience->publishedVersion?->config ?? $experience->draft_config ?? [], $store->timezone ?? 'UTC')[0];

        return app(BundleController::class)->versionEditor($store, $experience, $config, [], $this->versionMeta($experiment, $key));
    }

    public function saveVersion(Request $request, Store $store, int $experiment, string $key): Page|RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        abort_unless(ExperimentManager::priced($experiment->experience) && in_array($key, ['B', 'C'], true), 404);
        $input = $request->input('config');
        $input = is_string($input) ? json_decode($input, true) : $input;
        $errors = $this->manager->saveVersion($experiment, $key, is_array($input) ? $input : []);
        if ($errors) {
            $config = $experiment->fresh('variants')->variants->firstWhere('key', $key)?->config ?? (is_array($input) ? $input : []);

            return app(BundleController::class)->versionEditor($store, $experiment->experience, \App\Experiences\BundleSchema::normalize($config, $store->timezone ?? 'UTC')[0], $errors, $this->versionMeta($experiment, $key));
        }

        return redirect()->to(app_route('app.experiments.edit', ['experiment' => $experiment->id, 'notice' => 'saved']));
    }

    private function versionMeta(Experiment $experiment, string $key): array
    {
        return ['experiment' => $experiment->id, 'key' => $key, 'name' => $experiment->name, 'editable' => in_array($experiment->status, ['draft', 'paused'], true)];
    }

    public function duplicate(Request $request, Store $store, int $experiment): RedirectResponse
    {
        $copy = $this->manager->duplicate($this->find($store, $experiment), $this->user($request));

        return redirect()->to(app_route('app.experiments.edit', ['experiment' => $copy->id, 'notice' => 'created']));
    }

    public function destroy(Request $request, Store $store, int $experiment): RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        if (in_array($experiment->status, ['running', 'paused'], true)) {
            return redirect()->to(app_route('app.experiments.show', ['experiment' => $experiment->id, 'error' => 'Stop the test before deleting it.']));
        }
        $experiment->delete();

        return redirect()->to(app_route('app.experiments.index', ['tab' => 'completed', 'notice' => 'deleted']));
    }

    public function export(Request $request, Store $store, int $experiment, Results $results): StreamedResponse
    {
        $experiment = $this->find($store, $experiment);
        $r = $results->for($experiment);

        return response()->streamDownload(function () use ($experiment, $r) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Variant', 'Name', 'Allocation %', 'Visitors', 'Conversions', 'Conversion rate %', 'Orders', 'Revenue', 'Revenue per visitor', 'AOV', 'Lift vs control %', 'p-value']);
            foreach ($experiment->variants as $v) {
                $m = $r['variants'][$v->key];
                $c = $r['comparisons'][$v->key][Results::primaryKey($experiment)] ?? null;
                fputcsv($out, [$v->key, $v->name, $v->allocation, $m['visitors'], $m['conversions'], round((float) $m['conversion_rate'], 3), $m['orders'], $m['revenue'],
                    round((float) $m['revenue_per_visitor'], 4), round((float) $m['aov'], 2), $c ? round((float) $c['lift'], 2) : '', $c ? round($c['p'], 5) : '']);
            }
            fclose($out);
        }, 'ab-test-'.$experiment->handle.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function setup(Store $store, Experiment $experiment, array $errors, ?string $banner = null): Page
    {
        $experience = $experiment->experience;
        $type = Registry::has($experience->type) ? Registry::type($experience->type) : null;
        $config = $experience->publishedVersion?->config ?? $experience->draft_config ?? [];

        return page('experiments/edit', [
            'experiment' => [
                'id' => $experiment->id, 'name' => $experiment->name, 'hypothesis' => $experiment->hypothesis, 'status' => $experiment->status,
                'audience' => (object) ($experiment->audience ?? []), 'primary_metric' => $experiment->primary_metric,
                'secondary_metrics' => array_values($experiment->secondary_metrics ?? []), 'guardrails' => array_values($experiment->guardrails ?? []),
                'min_days' => $experiment->min_days, 'min_visitors' => $experiment->min_visitors, 'min_conversions' => $experiment->min_conversions,
                'ends_at' => $experiment->ends_at?->toDateString(),
                'variants' => $experiment->variants->map(fn ($v) => ['key' => $v->key, 'name' => $v->name, 'allocation' => $v->allocation, 'hidden' => (bool) $v->hidden,
                    'template_key' => $v->template_key, 'content' => (object) ($v->content ?? []), 'design' => (object) ($v->design ?? []), 'changes' => $v->changes ?? []])->keyBy('key'),
            ],
            'experience' => ['name' => $experience->name, 'type' => $type['label'] ?? $experience->type, 'published' => $experience->status === 'published', 'template_key' => $experience->template_key],
            'config' => ['content' => (object) ($config['content'] ?? []), 'design' => (object) ($config['design'] ?? [])],
            'templates' => collect($type['templates'] ?? [])->map(fn ($t, $k) => ['key' => $k, 'name' => $t['name']])->values(),
            'textFields' => Registry::has($experience->type) ? ExperimentManager::textFields($experience->type) : [],
            'designFields' => Registry::has($experience->type) ? (Schema::fields($experience->type)['design'] ?? []) : [],
            'audienceFields' => array_intersect_key(Schema::shared()['targeting'], array_flip(ExperimentManager::audienceFor($experience))),
            'checkoutBlock' => in_array($type['surface'] ?? '', ['checkout', 'thank-you'], true),
            'canHoldout' => ExperimentManager::canHoldout($experience),
            // G39: versions of a priced offer are full offers, edited in the bundle editor.
            'priced' => ExperimentManager::priced($experience),
            'tiers' => ExperimentManager::priced($experience) ? $this->tierSummary($experiment, $store) : null,
            'problems' => $this->manager->launchProblems($experiment),
            'fieldErrors' => (object) $errors,
            'banner' => $banner,
            'previews' => (object) $this->previews($experiment),
            'labels' => ['primary' => ExperimentManager::PRIMARY, 'secondary' => ExperimentManager::SECONDARY, 'guardrails' => ExperimentManager::GUARDRAILS],
            'segments' => \App\Models\Audiences\Segment::where('store_id', $store->id)->active()->orderBy('name')->get(['id', 'name']),
            'currency' => $store->currency ?? 'USD',
            'docsUrl' => route('site.docs', 'ab-testing'),
        ], $errors ? 422 : 200);
    }

    /** Each version's tiers in words ("10% off · 1 gift"), for comparing versions of a priced offer. */
    private function tierSummary(Experiment $experiment, Store $store): array
    {
        $experience = $experiment->experience;
        $base = \App\Experiences\BundleSchema::normalize($experience->publishedVersion?->config ?? $experience->draft_config ?? [], $store->timezone ?? 'UTC')[0];
        $money = fn ($v) => number_format((float) $v, 2).' '.($store->currency ?? '');
        $describe = function (array $c) use ($money) {
            if ($c['bundle_type'] === 'mix-tiers') {
                return array_map(fn ($t) => [$t['count'].' items', match ($c['mt']['rule']) { 'free' => $t['value'].' free', 'percent' => $t['value'].'% off', default => $money($t['value']).' for the set' }], $c['mt']['tiers']);
            }

            return array_map(fn ($o) => [$o['title'] ?: 'Offer', trim(match ($o['discount_type']) {
                'percentage' => $o['discount_value'].'% off', 'amount' => $money($o['discount_value']).' off', 'fixed_price' => $money($o['discount_value']).' total', default => 'Full price',
            }.(count($o['gifts']) ? ' · '.count($o['gifts']).' '.(count($o['gifts']) === 1 ? 'gift' : 'gifts') : ''))], array_values(array_filter($c['offers'], fn ($o) => $o['visible'])));
        };

        return ['A' => $describe($base)] + $experiment->variants->where('key', '!=', 'A')->mapWithKeys(fn ($v) => [$v->key => $v->config ? $describe(\App\Experiences\BundleSchema::normalize($v->config)[0]) : $describe($base)])->all();
    }

    /** Each variant as the storefront renders it, for side-by-side previews. */
    private function previews(Experiment $experiment): array
    {
        $experience = $experiment->experience;
        if (ExperimentManager::priced($experience)) {
            $base = $experience->publishedVersion?->config ?? $experience->draft_config ?? [];

            return $experiment->variants->mapWithKeys(fn ($v) => [$v->key => $v->hidden ? null : ['id' => $experience->handle.'-'.$v->key, 'type' => 'bundles', 'template' => 'editor', 'style' => 'vertical', 'version' => 0, 'priority' => 50]
                + \App\Services\Experiences\TemplateLibrary::bundlePreviewFrom(\App\Experiences\BundleSchema::normalize($v->config ?? $base)[0])])->all();
        }
        $config = $experience->publishedVersion?->config ?? $experience->draft_config;
        $control = $experience->publishedVersion?->template_key ?? $experience->template_key;

        return $experiment->variants->mapWithKeys(fn ($v) => [$v->key => $v->hidden ? null : [
            'id' => $experience->handle.'-'.$v->key, 'type' => $experience->type, 'template' => $v->template_key ?? $control,
            'style' => Registry::template($experience->type, $v->template_key ?? $control)['style'] ?? 'card', 'version' => 0, 'priority' => 50,
            'content' => array_merge($config['content'] ?? [], $v->content ?? []), 'design' => array_merge($config['design'] ?? [], $v->design ?? []),
            'behavior' => $config['behavior'] ?? [], 'targeting' => [], 'analytics' => ['track_views' => false, 'track_clicks' => false],
        ]])->all();
    }

    private function testable(Store $store)
    {
        return Experience::where('store_id', $store->id)->where('status', 'published')->orderBy('name')->get()
            ->filter(fn ($e) => ExperimentManager::unsupported($e) === null);
    }

    private function find(Store $store, int $id): Experiment
    {
        return Experiment::with(['experience.publishedVersion', 'variants'])->where('store_id', $store->id)->findOrFail($id);
    }

    private function user(Request $request)
    {
        return $request->attributes->get('storeUser');
    }
}
