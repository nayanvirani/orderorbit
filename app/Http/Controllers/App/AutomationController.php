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
 * Automation (Phase 7): workflows, templates, runs and logs, the team inbox and workflow emails.
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
        $flow = $this->flow($store);
        $since = now()->subDays(30);

        return page($flow ? 'automation/flows' : 'automation/index', $this->shared($store) + [
            'workflows' => $workflows->map(fn ($w) => [
                'id' => $w->id, 'name' => $w->name, 'status' => $w->status, 'trigger' => $triggers[$w->trigger]['label'] ?? $w->trigger,
                'unpublished' => $w->has_unpublished_changes && $w->published_version_id, 'runs' => $w->runs_30d, 'failed' => $w->failed_30d, 'last_run_at' => $w->last_run_at,
                'published' => (bool) $w->published_version_id, 'pills' => $flow ? self::pills((array) $w->draft) : null,
            ]),
            'plan' => ['name' => config('shopify.billing.plans.'.$store->effectivePlan().'.name'), 'workflows' => $store->planLimit('workflows')],
            'emails' => $flow ? AutomationEmail::where('store_id', $store->id)->where('created_at', '>=', $since)->selectRaw("count(*) as total, sum(case when status = 'sent' then 1 else 0 end) as sent")->first()?->only(['total', 'sent']) : null,
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

        $flow = $this->flow($store);
        $used = $flow ? Workflow::where('store_id', $store->id)->whereNotNull('template')->pluck('template')->unique()->all() : [];

        return page($flow ? 'automation/flow-templates' : 'automation/templates', $this->shared($store) + ['templates' => collect(Definition::templates())->map(fn ($t, $key) => [
            'key' => $key, 'name' => $t['name'], 'description' => $t['description'], 'trigger' => $triggers[$t['trigger']]['label'],
            'steps' => array_map(fn ($s) => Definition::describe($s), array_slice($t['steps'], 0, 3)),
            'category' => $t['category'] ?? 'care', 'pills' => $flow ? self::pills($t) : null, 'in_use' => in_array($key, $used, true),
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
                    return $this->editor($store, $workflow->fresh(), [], 'Draft saved. Automation isn\'t on your plan: upgrade to publish.');
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

        $flow = $this->flow($store);
        $selected = $flow ? WorkflowRun::where('store_id', $store->id)->find($request->integer('run') ?: ($runs->first()['id'] ?? 0)) : null;

        return page($flow ? 'automation/flow-runs' : 'automation/runs', $this->shared($store) + [
            'runs' => $runs, 'status' => (string) $request->query('status', ''), 'workflow' => (int) $request->query('workflow'),
            'workflowOptions' => Workflow::where('store_id', $store->id)->orderBy('name')->get(['id', 'name']),
            'totals' => $flow ? [
                'runs' => WorkflowRun::where('store_id', $store->id)->where('test', false)->where('created_at', '>=', now()->subDays(30))->count(),
                'failed' => WorkflowRun::where('store_id', $store->id)->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
                'waiting' => WorkflowRun::where('store_id', $store->id)->where('status', 'waiting')->count(),
            ] : null,
            'run' => $selected ? $this->runDetail($selected) : null,
        ]);
    }

    public function run(Request $request, Store $store, int $run): Page
    {
        $run = WorkflowRun::with(['workflow', 'version', 'logs' => fn ($q) => $q->orderBy('id')])->where('store_id', $store->id)->findOrFail($run);
        if ($this->flow($store)) {
            // Run history shows the list and the chosen run side by side.
            return $this->runs($request->merge(['run' => $run->id]), $store);
        }

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
        $emails->setCollection($emails->getCollection()->map(fn ($e) => [
            'id' => $e->id, 'subject' => $e->subject, 'body' => $e->body, 'customer_id' => $e->customer_id, 'to' => $e->to_email,
            'status' => $e->status, 'created_at' => $e->created_at, 'sent_at' => $e->sent_at,
            // Provider errors are for us; merchants see a plain reason.
            'reason' => in_array($e->status, ['failed', 'expired'], true) ? $e->error : null,
        ]));
        $sending = \App\Support\EmailSettings::get()['enabled'] && \App\Models\Mail\EmailProvider::where('is_active', true)->exists();

        return page('automation/emails', $this->shared($store) + ['emails' => $emails, 'sending' => $sending]);
    }

    private function editor(Store $store, Workflow $workflow, array $errors, ?string $banner = null): Page
    {
        // The flow-style editor (automation redesign), released with the flow_editor flag; it reads and saves the same definition.
        $flow = \App\Support\Features::enabled('flow_editor', $store);
        $live = $workflow->published_version_id ? $workflow->versions()->find($workflow->published_version_id) : null;
        $since = now()->subDays(30);

        return page($flow ? 'automation/flow' : 'automation/editor', $this->shared($store) + [
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
            'live' => $live ? ['version' => $live->version, 'published_at' => $live->published_at] : null,
            'savedAt' => $workflow->updated_at,
            'gates' => ['branching' => $store->planIncludes('automation_branching'), 'webhooks' => $store->planIncludes('automation_webhooks')],
            'stats' => $flow ? [
                'runs' => $workflow->runs()->where('test', false)->where('created_at', '>=', $since)->count(),
                'failed' => $workflow->runs()->where('test', false)->where('status', 'failed')->where('created_at', '>=', $since)->count(),
                'matched' => \App\Models\Automation\WorkflowLog::whereIn('run_id', $workflow->runs()->where('test', false)->where('created_at', '>=', $since)->select('id'))
                    ->where('kind', 'condition')->where('status', 'ok')->distinct()->count('run_id'),
            ] : null,
        ], $errors ? 422 : 200);
    }

    private function flow(Store $store): bool
    {
        return \App\Support\Features::enabled('flow_editor', $store);
    }

    /**
     * The workflow as coloured pills for lists: trigger, then each step in order (branches included).
     *
     * @return list<array{kind: string, label: string}>
     */
    public static function pills(array $definition): array
    {
        $catalog = Definition::catalog();
        $out = [['kind' => 'trigger', 'label' => $catalog['triggers'][$definition['trigger'] ?? '']['label'] ?? 'Trigger']];
        $walk = function (array $steps) use (&$walk, &$out, $catalog) {
            foreach ($steps as $s) {
                $out[] = match ($s['type'] ?? null) {
                    'condition' => ['kind' => 'condition', 'label' => $s['label'] ?? ($catalog['conditions'][$s['rules'][0]['field'] ?? '']['label'] ?? 'Condition').'?'],
                    'wait' => ['kind' => 'wait', 'label' => $s['label'] ?? 'Wait '.$s['amount'].' '.($s['amount'] == 1 ? rtrim($s['unit'], 's') : $s['unit'])],
                    default => ['kind' => 'action', 'label' => $s['label'] ?? ($catalog['actions'][$s['action'] ?? '']['label'] ?? 'Action')],
                };
                if (($s['type'] ?? null) === 'condition') {
                    $walk(array_merge($s['then'] ?? [], $s['else'] ?? []));
                }
            }
        };
        $walk((array) ($definition['steps'] ?? []));

        return $out;
    }

    /** A run step by step in plain words, with steps it never reached. */
    private function runDetail(WorkflowRun $run): array
    {
        $run->loadMissing(['workflow', 'version', 'logs' => fn ($q) => $q->orderBy('id')]);
        $program = (array) ($run->version?->program ?? []);
        $logged = $run->logs->where('kind', '!=', 'end')->pluck('step')->unique()->all();
        $finished = in_array($run->status, ['completed', 'failed', 'skipped'], true);
        $kind = fn ($k) => match ($k) { 'condition' => 'condition', 'wait' => 'wait', 'end' => 'end', default => 'action' };
        // "→ no" with nothing after it on the Otherwise side ends the run; say so.
        $ended = fn ($l) => $l->kind === 'condition' && $l->status !== 'ok' && ($program[$l->step]['else'] ?? -1) >= count($program);
        $steps = $run->logs->map(fn ($l) => [
            'id' => $l->id, 'kind' => $kind($l->kind), 'created_at' => $l->created_at,
            'message' => $l->message.($ended($l) ? '. The Otherwise branch is empty, so the run ended.' : ''),
            'status' => match (true) {
                $l->kind === 'condition' => $l->status === 'ok' ? 'matched' : 'no_match',
                $l->kind === 'end' => $l->status === 'ok' ? 'finished' : 'stopped',
                default => ['ok' => 'finished', 'skipped' => 'skipped', 'waiting' => 'waiting', 'failed' => 'failed'][$l->status] ?? $l->status,
            },
        ])->values()->all();
        if ($finished) {
            // Steps it never reached go before the closing "Workflow finished" line.
            $end = array_values(array_filter($steps, fn ($s) => $s['kind'] === 'end'));
            $steps = array_values(array_filter($steps, fn ($s) => $s['kind'] !== 'end'));
            foreach ($program as $i => $node) {
                if (($node['t'] ?? null) !== 'jump' && ! in_array($i, $logged, true)) {
                    $steps[] = ['id' => 'n'.$i, 'kind' => $kind($node['t'] === 'cond' ? 'condition' : $node['t']), 'message' => Definition::describe($node), 'status' => 'not_reached', 'created_at' => null];
                }
            }
            $steps = array_merge($steps, $end);
        }

        return [
            'id' => $run->id, 'test' => (bool) $run->test, 'workflow_id' => $run->workflow_id, 'workflow' => $run->workflow?->name,
            'version' => $run->version?->version ?: null, 'subject' => $run->subject, 'status' => $run->status,
            'resume_at' => $run->status === 'waiting' ? $run->resume_at : null,
            'trigger' => Definition::catalog()['triggers'][$run->trigger]['label'] ?? $run->trigger,
            'created_at' => $run->created_at, 'finished_at' => $run->finished_at, 'duration' => $run->duration(), 'error' => $run->error,
            'can_retry' => $run->canRetry(), 'steps' => $steps,
        ];
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
