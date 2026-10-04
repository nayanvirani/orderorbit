<?php

namespace App\Http\Controllers\App;

use App\Automation\Definition;
use App\Automation\WorkflowManager;
use App\Http\Controllers\Controller;
use App\Models\Automation\AutomationEmail;
use App\Models\Automation\InboxItem;
use App\Models\Automation\Workflow;
use App\Models\Automation\WorkflowRun;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Automation (Phase 7): workflows, templates, runs and logs, the team inbox and prepared emails.
 */
class AutomationController extends Controller
{
    public function __construct(private readonly WorkflowManager $workflows) {}

    public function index(Request $request, Store $store): View
    {
        $workflows = Workflow::where('store_id', $store->id)->withCount(['runs as runs_30d' => fn ($q) => $q->where('test', false)->where('created_at', '>=', now()->subDays(30))])
            ->withCount(['runs as failed_30d' => fn ($q) => $q->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))])
            ->latest('updated_at')->get();

        return view('app.automation.index', $this->shared($store) + [
            'workflows' => $workflows,
            'stats' => [
                'enabled' => $workflows->where('status', 'enabled')->count(),
                'runs' => WorkflowRun::where('store_id', $store->id)->where('test', false)->where('created_at', '>=', now()->subDays(30))->count(),
                'waiting' => WorkflowRun::where('store_id', $store->id)->where('status', 'waiting')->count(),
                'failed' => WorkflowRun::where('store_id', $store->id)->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function templates(Request $request, Store $store): View
    {
        return view('app.automation.templates', $this->shared($store) + ['templates' => Definition::templates()]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $workflow = $this->workflows->create($store, $request->input('template'), $this->user($request), $request->input('name'));

        return redirect()->to(app_route('app.automation.edit', ['workflow' => $workflow->id, 'notice' => 'created']));
    }

    public function edit(Request $request, Store $store, int $workflow): View
    {
        return $this->editor($store, $this->find($store, $workflow), []);
    }

    public function update(Request $request, Store $store, int $workflow): View|RedirectResponse
    {
        $workflow = $this->find($store, $workflow);
        $input = json_decode((string) $request->input('definition'), true);
        $errors = $this->workflows->saveDraft($workflow, is_array($input) ? $input : [], $request->input('name'));
        $action = $request->input('action', 'save');

        if ($errors) {
            return $this->editor($store, $workflow->fresh(), $errors, 'Draft saved. Fix the highlighted steps before you '.($action === 'test' ? 'test' : 'publish').'.');
        }

        try {
            if ($action === 'publish') {
                if (! $store->planIncludes('automation')) {
                    return $this->editor($store, $workflow->fresh(), [], 'Draft saved. Workflows run on the Scale plan: upgrade to publish.');
                }
                $version = $this->workflows->publish($workflow->fresh(), $this->user($request));

                return redirect()->to(app_route('app.automation.edit', ['workflow' => $workflow->id, 'notice' => 'published', 'published' => $version->version]));
            }
            if ($action === 'test') {
                $run = $this->workflows->test($workflow->fresh());

                return redirect()->to(app_route('app.automation.runs.show', ['run' => $run->id]));
            }
        } catch (RuntimeException $e) {
            return $this->editor($store, $workflow->fresh(), [], $e->getMessage());
        }

        return redirect()->to(app_route('app.automation.edit', ['workflow' => $workflow->id, 'notice' => 'saved']));
    }

    public function toggle(Request $request, Store $store, int $workflow): RedirectResponse
    {
        $workflow = $this->find($store, $workflow);
        try {
            $this->workflows->setEnabled($workflow, $workflow->status !== 'enabled');
        } catch (RuntimeException) {
            return redirect()->to(app_route('app.automation.edit', ['workflow' => $workflow->id, 'notice' => 'publish_unavailable']));
        }

        return redirect()->to(app_route('app.automation.index', ['notice' => $workflow->fresh()->status === 'enabled' ? 'resumed' : 'paused']));
    }

    public function restore(Request $request, Store $store, int $workflow, int $version): RedirectResponse
    {
        $workflow = $this->find($store, $workflow);
        $this->workflows->restore($workflow, $workflow->versions()->where('version', '>', 0)->findOrFail($version));

        return redirect()->to(app_route('app.automation.edit', ['workflow' => $workflow->id, 'notice' => 'version_restored']));
    }

    public function destroy(Request $request, Store $store, int $workflow): RedirectResponse
    {
        $workflow = $this->find($store, $workflow);
        $workflow->delete();

        return redirect()->to(app_route('app.automation.index', ['notice' => 'archived']));
    }

    public function runs(Request $request, Store $store): View
    {
        $runs = WorkflowRun::with('workflow')->where('store_id', $store->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('workflow'), fn ($q, $w) => $q->where('workflow_id', (int) $w))
            ->latest('id')->paginate(50)->withQueryString();

        return view('app.automation.runs', $this->shared($store) + ['runs' => $runs]);
    }

    public function run(Request $request, Store $store, int $run): View
    {
        $run = WorkflowRun::with(['workflow', 'version', 'logs' => fn ($q) => $q->orderBy('id')])->where('store_id', $store->id)->findOrFail($run);

        return view('app.automation.run', $this->shared($store) + ['run' => $run]);
    }

    public function inbox(Request $request, Store $store): View
    {
        return view('app.automation.inbox', $this->shared($store) + [
            'open' => InboxItem::where('store_id', $store->id)->whereNull('done_at')->latest('id')->limit(200)->get(),
            'done' => InboxItem::where('store_id', $store->id)->whereNotNull('done_at')->latest('done_at')->limit(30)->get(),
        ]);
    }

    public function done(Request $request, Store $store, int $item): RedirectResponse
    {
        InboxItem::where('store_id', $store->id)->findOrFail($item)->forceFill(['done_at' => now()])->save();

        return redirect()->to(app_route('app.automation.inbox'));
    }

    public function emails(Request $request, Store $store): View
    {
        return view('app.automation.emails', $this->shared($store) + [
            'emails' => AutomationEmail::where('store_id', $store->id)->latest('id')->paginate(50),
        ]);
    }

    private function editor(Store $store, Workflow $workflow, array $errors, ?string $banner = null): View
    {
        return view('app.automation.editor', $this->shared($store) + [
            'workflow' => $workflow,
            'catalog' => Definition::catalog(),
            'errors' => $errors,
            'banner' => $banner,
            'versions' => $workflow->versions()->where('version', '>', 0)->orderByDesc('version')->get(),
            'recentRuns' => $workflow->runs()->latest('id')->limit(10)->get(),
            'otherWorkflows' => Workflow::where('store_id', $store->id)->whereKeyNot($workflow->id)->orderBy('name')->get(['handle', 'name']),
        ]);
    }

    private function shared(Store $store): array
    {
        return [
            'store' => $store,
            'automationOn' => $store->planIncludes('automation'),
            'openTasks' => InboxItem::where('store_id', $store->id)->whereNull('done_at')->count(),
        ];
    }

    private function find(Store $store, int $id): Workflow
    {
        return Workflow::where('store_id', $store->id)->findOrFail($id);
    }

    private function user(Request $request)
    {
        return $request->attributes->get('storeUser');
    }
}
