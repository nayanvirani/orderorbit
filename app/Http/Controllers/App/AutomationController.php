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
use App\Support\Spa\Page;
use RuntimeException;
use Throwable;

/**
 * Automation (Phase 7): workflows, templates, runs and logs, the team inbox and prepared emails.
 */
class AutomationController extends Controller
{
    public function __construct(private readonly WorkflowManager $workflows) {}

    public function index(Request $request, Store $store): Page
    {
        $workflows = Workflow::where('store_id', $store->id)->withCount(['runs as runs_30d' => fn ($q) => $q->where('test', false)->where('created_at', '>=', now()->subDays(30))])
            ->withCount(['runs as failed_30d' => fn ($q) => $q->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))])
            ->latest('updated_at')->get();

        $triggers = Definition::catalog()['triggers'];

        return page('automation/index', $this->shared($store) + [
            'workflows' => $workflows->map(fn ($w) => [
                'id' => $w->id, 'name' => $w->name, 'status' => $w->status, 'trigger' => $triggers[$w->trigger]['label'] ?? $w->trigger,
                'unpublished' => $w->has_unpublished_changes && $w->published_version_id, 'runs' => $w->runs_30d, 'failed' => $w->failed_30d, 'last_run_at' => $w->last_run_at,
            ]),
            'stats' => [
                'enabled' => $workflows->where('status', 'enabled')->count(),
                'runs' => WorkflowRun::where('store_id', $store->id)->where('test', false)->where('created_at', '>=', now()->subDays(30))->count(),
                'waiting' => WorkflowRun::where('store_id', $store->id)->where('status', 'waiting')->count(),
                'failed' => WorkflowRun::where('store_id', $store->id)->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function templates(Request $request, Store $store): Page
    {
        $triggers = Definition::catalog()['triggers'];

        return page('automation/templates', $this->shared($store) + ['templates' => collect(Definition::templates())->map(fn ($t, $key) => [
            'key' => $key, 'name' => $t['name'], 'description' => $t['description'], 'trigger' => $triggers[$t['trigger']]['label'],
            'steps' => array_map(fn ($s) => Definition::describe($s), array_slice($t['steps'], 0, 3)),
        ])->values()]);
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        $workflow = $this->workflows->create($store, $request->input('template'), $this->user($request), $request->input('name'));

        return redirect()->to(app_route('app.automation.edit', ['workflow' => $workflow->id, 'notice' => 'created']));
    }

    public function edit(Request $request, Store $store, int $workflow): Page
    {
        return $this->editor($store, $this->find($store, $workflow), []);
    }

    public function update(Request $request, Store $store, int $workflow): Page|RedirectResponse
    {
        $workflow = $this->find($store, $workflow);
        $input = $request->input('definition');
        $input = is_string($input) ? json_decode($input, true) : $input;
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

    public function runs(Request $request, Store $store): Page
    {
        $runs = WorkflowRun::with('workflow')->where('store_id', $store->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('workflow'), fn ($q, $w) => $q->where('workflow_id', (int) $w))
            ->latest('id')->paginate(50)->withQueryString();
        $triggers = Definition::catalog()['triggers'];
        $runs->setCollection($runs->getCollection()->map(fn (WorkflowRun $r) => [
            'id' => $r->id, 'test' => (bool) $r->test, 'workflow' => $r->workflow?->name, 'trigger' => $triggers[$r->trigger]['label'] ?? $r->trigger,
            'subject' => $r->subject, 'status' => $r->status, 'resume_at' => $r->status === 'waiting' ? $r->resume_at : null, 'created_at' => $r->created_at, 'duration' => $r->duration(),
        ]));

        return page('automation/runs', $this->shared($store) + [
            'runs' => $runs, 'status' => (string) $request->query('status', ''), 'workflow' => (int) $request->query('workflow'),
            'workflowOptions' => Workflow::where('store_id', $store->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function run(Request $request, Store $store, int $run): Page
    {
        $run = WorkflowRun::with(['workflow', 'version', 'logs' => fn ($q) => $q->orderBy('id')])->where('store_id', $store->id)->findOrFail($run);

        return page('automation/run', $this->shared($store) + ['run' => [
            'id' => $run->id, 'test' => (bool) $run->test, 'workflow_id' => $run->workflow_id, 'workflow' => $run->workflow?->name,
            'version' => $run->version?->version ?: null, 'subject' => $run->subject, 'status' => $run->status,
            'resume_at' => $run->status === 'waiting' ? $run->resume_at : null,
            'trigger' => Definition::catalog()['triggers'][$run->trigger]['label'] ?? $run->trigger,
            'created_at' => $run->created_at, 'finished_at' => $run->finished_at, 'duration' => $run->duration(), 'attempts' => $run->attempts,
            'error' => $run->error, 'idempotency_key' => $run->idempotency_key, 'can_retry' => $run->canRetry(),
            'logs' => $run->logs->map(fn ($l) => ['id' => $l->id, 'step' => $l->kind === 'end' ? null : $l->step + 1, 'message' => $l->message, 'status' => $l->status, 'created_at' => $l->created_at]),
        ]]);
    }

    public function retry(Request $request, Store $store, int $run): RedirectResponse
    {
        $run = WorkflowRun::where('store_id', $store->id)->findOrFail($run);
        $run = app(\App\Automation\Engine::class)->retry($run);

        return redirect()->to(app_route('app.automation.runs.show', ['run' => $run->id, 'notice' => $run->error ? 'retry_failed' : 'retried']));
    }

    public function inbox(Request $request, Store $store): Page
    {
        $item = fn ($i) => ['id' => $i->id, 'title' => $i->title, 'body' => $i->body, 'kind' => $i->kind, 'due_at' => $i->due_at, 'done_at' => $i->done_at];

        return page('automation/inbox', $this->shared($store) + [
            'open' => InboxItem::where('store_id', $store->id)->whereNull('done_at')->latest('id')->limit(200)->get()->map($item),
            'done' => InboxItem::where('store_id', $store->id)->whereNotNull('done_at')->latest('done_at')->limit(30)->get()->map($item),
        ]);
    }

    public function done(Request $request, Store $store, int $item): RedirectResponse
    {
        InboxItem::where('store_id', $store->id)->findOrFail($item)->forceFill(['done_at' => now()])->save();

        return redirect()->to(app_route('app.automation.inbox'));
    }

    public function emails(Request $request, Store $store): Page
    {
        $emails = AutomationEmail::where('store_id', $store->id)->latest('id')->paginate(50);
        $emails->setCollection($emails->getCollection()->map(fn ($e) => ['id' => $e->id, 'subject' => $e->subject, 'body' => $e->body, 'customer_id' => $e->customer_id, 'status' => $e->status, 'created_at' => $e->created_at]));

        return page('automation/emails', $this->shared($store) + ['emails' => $emails]);
    }

    private function editor(Store $store, Workflow $workflow, array $errors, ?string $banner = null): Page
    {
        return page('automation/editor', $this->shared($store) + [
            'workflow' => [
                'id' => $workflow->id, 'name' => $workflow->name, 'status' => $workflow->status, 'draft' => (object) ($workflow->draft ?? []),
                'unpublished' => $workflow->has_unpublished_changes && $workflow->published_version_id, 'published' => (bool) $workflow->published_version_id,
            ],
            'catalog' => $this->catalogFor($store),
            'errors' => (object) $errors,
            'banner' => $banner,
            'versions' => $workflow->versions()->where('version', '>', 0)->orderByDesc('version')->get()->map(fn ($v) => [
                'id' => $v->id, 'version' => $v->version, 'published_at' => $v->published_at, 'live' => $v->id === $workflow->published_version_id,
            ]),
            'recentRuns' => $workflow->runs()->latest('id')->limit(10)->get()->map(fn ($r) => ['id' => $r->id, 'subject' => $r->subject, 'status' => $r->status, 'test' => (bool) $r->test]),
            'otherWorkflows' => Workflow::where('store_id', $store->id)->whereKeyNot($workflow->id)->orderBy('name')->get(['handle', 'name']),
        ], $errors ? 422 : 200);
    }

    /** The catalog with this store's segments as the "Customer segment" choices. */
    private function catalogFor(Store $store): array
    {
        $catalog = Definition::catalog();
        $segments = \App\Models\Audiences\Segment::where('store_id', $store->id)->active()->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all();
        $catalog['conditions']['segment'] = ['type' => 'select', 'options' => $segments ?: ['' => 'No segments yet: create one in Audiences']] + $catalog['conditions']['segment'];

        return $catalog;
    }

    private function shared(Store $store): array
    {
        return [
            'automationOn' => $store->planIncludes('automation'),
            'docsUrl' => route('site.docs', 'automation'),
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
