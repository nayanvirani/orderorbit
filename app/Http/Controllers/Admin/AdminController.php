<?php

namespace App\Http\Controllers\Admin;

use App\Experiences\Registry;
use App\Http\Controllers\App\SupportController;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\AuditLog;
use App\Models\Automation\WorkflowRun;
use App\Models\CroTemplate;
use App\Models\FeatureFlag;
use App\Models\Store;
use App\Models\Support\Attachment;
use App\Models\Support\Ticket;
use App\Models\User;
use App\Models\WebhookReceipt;
use App\Services\Support\SupportDesk;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Internal Admin (section 33) for the Growvia team: stores, subscriptions, usage, extension
 * health, workflow failures, analytics processing, template publishing, feature flags, support
 * and audit logs. Separate from the merchant app, behind an email-and-password team login.
 */
class AdminController extends Controller
{
    public function home(): View
    {
        $stores = Store::all();
        $installed = $stores->filter(fn ($s) => $s->isInstalled());
        $plans = collect(config('shopify.billing.plans'));
        // Paying = a real subscription (not a test store or a complimentary plan).
        $paying = $installed->filter(fn ($s) => $s->plan && ! $s->compPlan() && ! $s->isTestShop());
        $installsByDay = Store::where('installed_at', '>=', now()->subDays(29)->startOfDay())->selectRaw('date(installed_at) as day, count(*) as n')->groupBy('day')->pluck('n', 'day');

        return view('admin.home', [
            'kpis' => [
                'installed' => $installed->count(),
                'new_30d' => $stores->filter(fn ($s) => $s->installed_at?->gte(now()->subDays(30)))->count(),
                'uninstalled_30d' => $stores->filter(fn ($s) => $s->uninstalled_at?->gte(now()->subDays(30)))->count(),
                'mrr' => $paying->sum(fn ($s) => (float) ($plans[$s->plan]['price'] ?? 0)),
                'paying' => $paying->count(),
                'custom' => $installed->filter(fn ($s) => ! empty($s->entitlements))->count(),
                'upgrade_clicks_30d' => \App\Models\UpgradeEvent::where('created_at', '>=', now()->subDays(30))->count(),
                'upgrades_30d' => \App\Models\UpgradeEvent::where('converted_at', '>=', now()->subDays(30))->count(),
                'events_24h' => AnalyticsEvent::where('occurred_at', '>=', now()->subDay())->count(),
                'failed_runs_24h' => WorkflowRun::where('status', 'failed')->where('test', false)->where('updated_at', '>=', now()->subDay())->count(),
                'open_tickets' => Ticket::whereIn('status', ['open'])->count(),
                'webhooks_unprocessed' => WebhookReceipt::whereNull('processed_at')->where('created_at', '>=', now()->subDay())->count(),
                'pixel_off' => $installed->filter(fn ($s) => ! $s->web_pixel_id)->count(),
            ],
            'planMix' => $plans->map(fn ($p, $key) => ['name' => $p['name'], 'stores' => $installed->filter(fn ($s) => $s->effectivePlan() === $key)->count(), 'mrr' => $paying->filter(fn ($s) => $s->plan === $key)->count() * (float) $p['price']]),
            'noPlan' => $installed->filter(fn ($s) => ! $s->hasPlanAccess())->count(),
            'installsByDay' => collect(range(29, 0))->mapWithKeys(fn ($d) => [now()->subDays($d)->toDateString() => (int) ($installsByDay[now()->subDays($d)->toDateString()] ?? 0)]),
            'recentInstalls' => Store::whereNotNull('installed_at')->latest('installed_at')->limit(8)->get(),
            'waiting' => Ticket::with('store')->where('status', 'open')->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->oldest('last_reply_at')->limit(8)->get(),
        ]);
    }

    public function stores(Request $request): View
    {
        $stores = Store::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q->where('shop_domain', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->when($request->query('status') === 'installed', fn ($q) => $q->whereNotNull('installed_at')->whereNull('uninstalled_at'))
            ->when($request->query('status') === 'uninstalled', fn ($q) => $q->whereNotNull('uninstalled_at'))
            ->when($request->query('status') === 'custom', fn ($q) => $q->whereNotNull('entitlements'))
            ->when($request->query('plan'), fn ($q, $plan) => $plan === 'none' ? $q->whereNull('plan') : $q->where('plan', $plan))
            ->latest('installed_at')->paginate(50)->withQueryString();
        $ids = $stores->pluck('id');

        return view('admin.stores', [
            'stores' => $stores,
            'plans' => collect(config('shopify.billing.plans'))->map(fn ($p) => $p['name']),
            'events' => AnalyticsEvent::whereIn('store_id', $ids)->where('occurred_at', '>=', now()->subDays(7))->selectRaw('store_id, count(*) as n')->groupBy('store_id')->pluck('n', 'store_id'),
            'live' => \App\Models\Experience::whereIn('store_id', $ids)->where('status', 'published')->selectRaw('store_id, count(*) as n')->groupBy('store_id')->pluck('n', 'store_id'),
        ]);
    }

    public function store(int $store): View
    {
        $store = Store::findOrFail($store);

        return view('admin.store', [
            'store' => $store,
            'tab' => in_array(request('tab'), ['overview', 'access', 'activity'], true) ? request('tab') : 'overview',
            'plans' => collect(config('shopify.billing.plans')),
            'meters' => \App\Services\Usage::METERS,
            'usageNow' => collect(app(\App\Services\Usage::class)->summary($store))->keyBy('meter'),
            'users' => $store->users()->orderBy('role')->get(),
            'subscription' => \App\Models\Subscription::where('store_id', $store->id)->latest('id')->first(),
            'usage' => \App\Models\UsageRecord::where('store_id', $store->id)->latest('id')->limit(20)->get(),
            'experiences' => $store->experiences()->selectRaw('type, status, count(*) as n')->groupBy('type', 'status')->get(),
            'eventsByDay' => AnalyticsEvent::where('store_id', $store->id)->where('occurred_at', '>=', now()->subDays(14))->selectRaw('date(occurred_at) as day, count(*) as n')->groupBy('day')->orderBy('day')->pluck('n', 'day'),
            'failedRuns' => WorkflowRun::with('workflow')->where('store_id', $store->id)->where('status', 'failed')->latest('id')->limit(10)->get(),
            'webhooks' => WebhookReceipt::where('shop_domain', $store->shop_domain)->latest('id')->limit(15)->get(),
            'tickets' => Ticket::where('store_id', $store->id)->latest('id')->limit(10)->get(),
            'audit' => AuditLog::where('store_id', $store->id)->latest('id')->limit(25)->get(),
        ]);
    }

    public function failures(): View
    {
        return view('admin.failures', [
            'runs' => WorkflowRun::with(['workflow', 'store'])->where('status', 'failed')->where('test', false)->latest('updated_at')->paginate(50),
            'byError' => WorkflowRun::where('status', 'failed')->where('test', false)->where('updated_at', '>=', now()->subDays(7))->selectRaw('error, count(*) as n')->groupBy('error')->orderByDesc('n')->limit(10)->get(),
        ]);
    }

    public function analytics(): View
    {
        return view('admin.analytics', [
            'byDay' => AnalyticsEvent::where('occurred_at', '>=', now()->subDays(14))->selectRaw('date(occurred_at) as day, count(*) as n')->groupBy('day')->orderBy('day')->pluck('n', 'day'),
            'byName' => AnalyticsEvent::where('occurred_at', '>=', now()->subDay())->selectRaw('name, count(*) as n')->groupBy('name')->orderByDesc('n')->limit(20)->pluck('n', 'name'),
            'lastHour' => AnalyticsEvent::where('occurred_at', '>=', now()->subHour())->count(),
            'total' => AnalyticsEvent::count(),
            'oldest' => AnalyticsEvent::min('occurred_at'),
            'topStores' => AnalyticsEvent::with('store:id,shop_domain')->where('occurred_at', '>=', now()->subDay())->selectRaw('store_id, count(*) as n')->groupBy('store_id')->orderByDesc('n')->limit(10)->get(),
            'pixels' => ['connected' => Store::whereNotNull('web_pixel_id')->whereNull('uninstalled_at')->count(), 'installed' => Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->count()],
        ]);
    }

    public function templates(): View
    {
        $used = \App\Models\Experience::where('status', '!=', 'archived')->selectRaw('type, template_key, count(*) as n')->groupBy('type', 'template_key')->get()->mapWithKeys(fn ($r) => [$r->type.':'.$r->template_key => $r->n]);

        return view('admin.templates', [
            'templates' => CroTemplate::orderBy('type')->orderBy('key')->get()->filter(fn ($t) => Registry::has($t->type))->groupBy('type'),
            'used' => $used,
        ]);
    }

    public function toggleTemplate(int $template): RedirectResponse
    {
        $template = CroTemplate::findOrFail($template);
        $template->forceFill(['status' => $template->status === 'published' ? 'unpublished' : 'published'])->save();
        Cache::forget('templates:unpublished');
        AuditLog::record('admin.template_'.$template->status, null, ['template' => $template->type.':'.$template->key, 'by' => auth()->user()->email]);

        return back()->with('status', 'Template '.$template->status.'.');
    }

    public function flags(Request $request): View
    {
        $registry = Features::registry();
        $rows = FeatureFlag::all()->keyBy('key');
        $key = array_key_exists((string) $request->query('key'), $registry) && ! $registry[$request->query('key')]['kill'] ? $request->query('key') : array_key_first(array_filter($registry, fn ($f) => ! $f['kill']));

        return view('admin.flags', [
            'registry' => $registry, 'rows' => $rows, 'key' => $key, 'flag' => $rows[$key] ?? null,
            'unknown' => $rows->reject(fn ($r) => array_key_exists($r->key, $registry)),
            'stores' => Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->orderBy('shop_domain')->get(['id', 'shop_domain']),
            'history' => AuditLog::whereIn('action', ['admin.flag_saved', 'admin.kill_switch'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function saveFlag(Request $request): RedirectResponse
    {
        $release = array_keys(array_filter(Features::registry(), fn ($f) => ! $f['kill']));
        $data = $request->validate([
            'key' => ['required', 'in:'.implode(',', $release)],
            'mode' => ['required', 'in:'.implode(',', array_keys(FeatureFlag::MODES))],
            'store_ids' => ['array'], 'store_ids.*' => ['integer'],
            'percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], ['key.in' => 'Choose a flag from the list.', 'ends_at.after' => 'The turn-off time must be after the turn-on time.']);
        if ($data['mode'] === 'stores' && empty($data['store_ids'])) {
            return back()->withInput()->withErrors(['store_ids' => 'Choose at least one store.']);
        }
        if ($data['mode'] === 'percent' && empty($data['percent'])) {
            return back()->withInput()->withErrors(['percent' => 'Choose a share of stores.']);
        }
        $ids = Store::whereIn('id', $data['store_ids'] ?? [])->pluck('id')->all();
        $flag = FeatureFlag::updateOrCreate(['key' => $data['key']], [
            'mode' => $data['mode'], 'enabled' => $data['mode'] === 'all', 'store_ids' => $data['mode'] === 'stores' ? $ids : null,
            'percent' => $data['mode'] === 'percent' ? (int) $data['percent'] : 0,
            'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null,
            'description' => Features::registry()[$data['key']]['name'],
        ]);
        Features::forget();
        AuditLog::record('admin.flag_saved', null, ['flag' => $flag->key, 'mode' => $flag->mode, 'stores' => count($ids), 'percent' => $flag->percent,
            'starts_at' => $flag->starts_at?->toIso8601String(), 'ends_at' => $flag->ends_at?->toIso8601String(), 'by' => auth()->user()->email]);

        return redirect()->route('admin.flags', ['key' => $flag->key])->with('status', Features::registry()[$flag->key]['name'].' saved.');
    }

    /** Emergency off: hides a live part of Growvia for every store, then republishes every store. */
    public function killSwitch(Request $request, string $key): RedirectResponse
    {
        abort_unless(array_key_exists($key, config('flags.kill', [])), 404);
        $on = $request->boolean('on');
        FeatureFlag::updateOrCreate(['key' => $key], ['mode' => $on ? 'all' : 'off', 'enabled' => $on, 'store_ids' => null, 'percent' => 0, 'starts_at' => null, 'ends_at' => null, 'description' => config('flags.kill')[$key][0]]);
        Features::forget();
        AuditLog::record('admin.kill_switch', null, ['flag' => $key, 'on' => $on, 'by' => auth()->user()->email]);
        // Storefront, checkout and account data change for every store: push it now (the 5-minute job would catch up too).
        defer(function () {
            Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->lazyById(50)->each(fn (Store $s) => app(\App\Services\Billing\Entitlements::class)->apply($s));
        });

        return redirect()->route('admin.flags')->with('status', config('flags.kill')[$key][0].($on ? ' is OFF for every store.' : ' is back on for every store.'));
    }

    public function deleteFlag(int $flag): RedirectResponse
    {
        $flag = FeatureFlag::findOrFail($flag);
        $flag->delete();
        Features::forget();

        return redirect()->route('admin.flags')->with('status', 'Flag deleted.');
    }

    public function tickets(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'pending', 'resolved', 'closed', 'all'], true) ? $request->query('status') : 'open';

        return view('admin.tickets', [
            'tickets' => Ticket::with(['store', 'assignee'])->where('channel', 'ticket')->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
                ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->latest('last_reply_at')->paginate(50)->withQueryString(),
            'status' => $status,
            'counts' => Ticket::where('channel', 'ticket')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function ticket(Request $request, int $ticket): View
    {
        $ticket = Ticket::with(['store', 'requester', 'messages.attachments'])->findOrFail($ticket);
        $suggestions = null;
        if ($request->boolean('suggest')) {
            $last = $ticket->messages->where('author', 'merchant')->where('internal', false)->last();
            $suggestions = app(\App\Support\ReplySuggester::class)->suggest($ticket->subject.' '.($last?->body ?? ''),
                \App\Support\ReplySuggester::context(trim(($ticket->requester?->first_name ?? '')) ?: null, $ticket->store));
        }

        return view('admin.ticket', ['ticket' => $ticket, 'team' => User::where('is_admin', true)->orderBy('name')->get(), 'suggestions' => $suggestions,
            'customPlan' => $this->customPlanReview($ticket)]);
    }

    /**
     * A custom plan request (G34): each module marked "In their plan" or the plan it needs, the
     * closest plan, and reply templates the team edits before sending.
     */
    private function customPlanReview(Ticket $ticket): ?array
    {
        $request = $ticket->diagnostics['custom_plan'] ?? null;
        if (! is_array($request)) {
            return null;
        }
        $store = $ticket->store;
        $first = \App\Support\Plans::firstWith();
        $rows = array_map(fn ($key) => ['label' => \App\Support\Modules::label($key), 'has' => $store->planIncludes($key), 'needs' => $first[$key] ?? 'a private plan'], (array) ($request['modules'] ?? []));
        $missing = array_values(array_filter($rows, fn ($r) => ! $r['has']));
        $name = trim((string) ($ticket->requester?->first_name ?? '')) ?: 'there';
        $plan = config('shopify.billing.plans.'.$store->effectivePlan().'.name') ?: 'no plan yet';
        $closest = $request['closest'] ?? null;

        return ['rows' => $rows, 'closest' => $closest, 'widgets' => $request['widgets'] ?? null, 'runs' => $request['runs'] ?? null, 'budget' => $request['budget'] ?? null, 'plan' => $plan,
            'templates' => array_filter([
                'Already in your plan' => ! $missing ? "Hi {$name},\n\nGood news: your {$plan} plan already includes everything you asked for. You can turn each module on from its page in Growvia.\n\nIf anything doesn't work as expected, reply here and we'll help." : null,
                'Suggest '.($closest ?? 'a plan') => $closest && $closest !== $plan ? "Hi {$name},\n\nThanks for telling us what you need. The {$closest} plan includes ".implode(', ', array_column($missing, 'label')).". You can switch in Settings › Plan and billing; your settings and data stay as they are.\n\nWould that work for you?" : null,
                'Private plan' => "Hi {$name},\n\nNo standard plan fits this mix exactly, so we can set up a plan for your store: ".implode(', ', array_column($rows, 'label')).". We'll confirm the price and turn it on for you.\n\nDoes that sound good?",
            ])];
    }

    public function replyTicket(Request $request, int $ticket, SupportDesk $desk): RedirectResponse
    {
        $ticket = Ticket::findOrFail($ticket);
        $request->validate(['body' => ['required', 'string', 'max:10000']] + SupportDesk::ATTACHMENT_RULES);
        $desk->reply($ticket, 'team', $request->input('body'), (array) $request->file('attachments', []), $request->boolean('internal'), $request->user());
        if (! $request->boolean('internal')) {
            SavedReplyController::used($request->input('saved_reply_id'));
        }

        return redirect()->route('admin.ticket', $ticket->id)->with('status', $request->boolean('internal') ? 'Note added.' : 'Reply sent.');
    }

    public function updateTicket(Request $request, int $ticket): RedirectResponse
    {
        $ticket = Ticket::findOrFail($ticket);
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Ticket::STATUSES))], 'priority' => ['required', 'in:'.implode(',', array_keys(Ticket::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer'], 'resolution' => ['nullable', 'string', 'max:2000'],
        ]);
        $ticket->forceFill([
            'status' => $data['status'], 'priority' => $data['priority'],
            'assigned_to' => User::where('is_admin', true)->whereKey($data['assigned_to'] ?? 0)->value('id'),
            'resolution' => $data['resolution'] ?? null,
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null,
        ])->save();

        return redirect()->route('admin.ticket', $ticket->id)->with('status', 'Ticket updated.');
    }

    public function attachment(int $attachment): Response
    {
        return SupportController::download(Attachment::findOrFail($attachment));
    }

    public function audit(Request $request): View
    {
        return view('admin.audit', [
            'logs' => AuditLog::with('store:id,shop_domain')
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->when($request->query('store'), fn ($q, $d) => $q->whereHas('store', fn ($q) => $q->where('shop_domain', 'like', "%{$d}%")))
                ->latest('id')->paginate(100)->withQueryString(),
        ]);
    }
}
