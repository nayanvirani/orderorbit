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
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A/B testing (Phase 9): tests list, setup, results and lifecycle.
 */
class ExperimentController extends Controller
{
    public function __construct(private readonly ExperimentManager $manager) {}

    public function index(Request $request, Store $store, Results $results): View
    {
        $tab = in_array($request->query('tab'), ['active', 'drafts', 'completed'], true) ? $request->query('tab') : 'active';
        $statuses = ['active' => ['running', 'paused'], 'drafts' => ['draft'], 'completed' => ['completed', 'stopped']][$tab];
        $experiments = Experiment::with(['experience', 'variants'])->where('store_id', $store->id)->whereIn('status', $statuses)
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->latest('updated_at')->get();

        return view('app.experiments.index', [
            'store' => $store,
            'tab' => $tab,
            'experiments' => $experiments,
            'summaries' => $experiments->mapWithKeys(fn ($e) => [$e->id => $e->started_at ? $results->for($e) : null]),
            'counts' => Experiment::where('store_id', $store->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'testable' => $this->testable($store),
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

    public function edit(Request $request, Store $store, int $experiment): View|RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        if (in_array($experiment->status, ['running', 'completed', 'stopped'], true)) {
            return redirect()->to(app_route('app.experiments.show', ['experiment' => $experiment->id]));
        }

        return $this->setup($store, $experiment, []);
    }

    public function update(Request $request, Store $store, int $experiment): View|RedirectResponse
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

    public function show(Request $request, Store $store, int $experiment, Results $results): View|RedirectResponse
    {
        $experiment = $this->find($store, $experiment);
        if ($experiment->status === 'draft') {
            return redirect()->to(app_route('app.experiments.edit', ['experiment' => $experiment->id]));
        }

        return view('app.experiments.show', [
            'store' => $store,
            'experiment' => $experiment,
            'results' => $results->for($experiment),
            'logs' => $experiment->logs()->with('user')->limit(50)->get(),
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
                $c = $r['comparisons'][$v->key][$experiment->primary_metric === 'conversion_rate' ? 'conversion_rate' : 'revenue_per_visitor'] ?? null;
                fputcsv($out, [$v->key, $v->name, $v->allocation, $m['visitors'], $m['conversions'], round((float) $m['conversion_rate'], 3), $m['orders'], $m['revenue'],
                    round((float) $m['revenue_per_visitor'], 4), round((float) $m['aov'], 2), $c ? round((float) $c['lift'], 2) : '', $c ? round($c['p'], 5) : '']);
            }
            fclose($out);
        }, 'ab-test-'.$experiment->handle.'.csv', ['Content-Type' => 'text/csv']);
    }

    private function setup(Store $store, Experiment $experiment, array $errors, ?string $banner = null): View
    {
        $experience = $experiment->experience;
        $type = Registry::has($experience->type) ? Registry::type($experience->type) : null;

        return view('app.experiments.edit', [
            'store' => $store,
            'experiment' => $experiment,
            'experience' => $experience,
            'type' => $type,
            'templates' => $type['templates'] ?? [],
            'textFields' => Registry::has($experience->type) ? ExperimentManager::textFields($experience->type) : [],
            'designFields' => Registry::has($experience->type) ? (Schema::fields($experience->type)['design'] ?? []) : [],
            'audienceFields' => array_intersect_key(Schema::shared()['targeting'], array_flip(ExperimentManager::AUDIENCE)),
            'canHoldout' => ExperimentManager::canHoldout($experience),
            'problems' => $this->manager->launchProblems($experiment),
            'fieldErrors' => $errors,
            'banner' => $banner,
            'previews' => $this->previews($experiment),
        ]);
    }

    /** Each variant as the storefront renders it, for side-by-side previews. */
    private function previews(Experiment $experiment): array
    {
        $experience = $experiment->experience;
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
