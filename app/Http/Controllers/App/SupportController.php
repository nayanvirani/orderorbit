<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Support\Attachment;
use App\Models\Support\Ticket;
use App\Services\Support\SupportDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Support\Spa\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * Support (section 34) for merchants: open tickets, follow the conversation, reply and close.
 */
class SupportController extends Controller
{
    public function __construct(private readonly SupportDesk $desk) {}

    public function index(Request $request, Store $store): Page
    {
        return page('support/index', [
            'tickets' => Ticket::where('store_id', $store->id)->latest('updated_at')->get()->map(fn (Ticket $t) => [
                'id' => $t->id, 'reference' => $t->reference(), 'subject' => $t->subject, 'status' => $t->status,
                'category' => ($t->channel === 'chat' ? 'Live chat · ' : '').(Ticket::CATEGORIES[$t->category] ?? $t->category), 'updated_at' => $t->updated_at,
            ]),
            // Custom plan requests (G34) come with the premium_plan flag.
            'categories' => \App\Support\Features::enabled('premium_plan', $store) ? Ticket::CATEGORIES : array_diff_key(Ticket::CATEGORIES, ['custom_plan' => 1]),
            'customPlan' => \App\Support\Features::enabled('premium_plan', $store) ? self::customPlanOptions($store) : null,
            'category' => $request->query('category'),
            'priorities' => Ticket::PRIORITIES,
            'statuses' => Ticket::STATUSES,
            'helpUrl' => route('site.help'),
            'docsUrl' => route('site.docs.index'),
        ]);
    }

    /** For the Custom plan form: modules grouped like the menu with the cheapest plan for each, and the plans to compare. */
    public static function customPlanOptions(Store $store): array
    {
        $first = \App\Support\Plans::firstWith();
        $plans = collect(\App\Support\Plans::public())->sortBy('price');

        return [
            'current' => config('shopify.billing.plans.'.$store->effectivePlan().'.name'),
            'groups' => collect(\App\Support\Modules::all())->map(fn ($m, $key) => ['key' => $key, 'label' => $m[0], 'group' => $m[1], 'plan' => $first[$key] ?? null, 'coming' => in_array($key, \App\Support\Modules::COMING, true)])
                ->filter(fn ($m) => $m['plan'] !== null)->groupBy('group')->map->values()->all(),
            'plans' => $plans->map(fn ($p, $key) => ['key' => $key, 'name' => $p['name'], 'price' => (float) $p['price'], 'includes' => array_values($p['includes'] ?? []),
                'widgets' => $p['limits']['active_experiences'] ?? null, 'runs' => $p['limits']['automation_executions'] ?? null])->values()->all(),
        ];
    }

    public function store(Request $request, Store $store): RedirectResponse
    {
        if ($request->input('category') === 'custom_plan' && \App\Support\Features::enabled('premium_plan', $store)) {
            return $this->customPlan($request, $store);
        }
        $validator = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'in:'.implode(',', array_keys(Ticket::CATEGORIES))], 'priority' => ['nullable', 'in:'.implode(',', array_keys(Ticket::PRIORITIES))],
        ] + SupportDesk::ATTACHMENT_RULES, ['attachments.*.mimes' => 'Attach images, PDFs, text or CSV files.', 'attachments.*.max' => 'Each attachment can be up to 4 MB.']);
        $validator->validate();
        $ticket = $this->desk->open($store, $request->attributes->get('storeUser'), $validator->validated(), (array) $request->file('attachments', []));

        return redirect()->to(app_route('app.support.show', ['ticket' => $ticket->id, 'notice' => 'ticket_created']));
    }

    public function show(Request $request, Store $store, int $ticket): Page
    {
        $ticket = $this->find($store, $ticket);

        return page('support/show', [
            'ticket' => [
                'id' => $ticket->id, 'reference' => $ticket->reference(), 'subject' => $ticket->subject, 'status' => $ticket->status,
                'status_label' => Ticket::STATUSES[$ticket->status], 'category' => Ticket::CATEGORIES[$ticket->category] ?? $ticket->category,
                'priority' => Ticket::PRIORITIES[$ticket->priority] ?? $ticket->priority, 'created_at' => $ticket->created_at, 'resolution' => $ticket->resolution,
            ],
            'messages' => $ticket->messages()->where('internal', false)->with('attachments')->get()->map(fn ($m) => [
                'id' => $m->id, 'team' => $m->author === 'team', 'created_at' => $m->created_at, 'body' => $m->body,
                'author' => $m->author === 'team' ? 'Growvia team'.($m->author_name ? ' · '.$m->author_name : '') : ($m->author_name ?: 'You'),
                'attachments' => $m->attachments->map(fn ($a) => ['id' => $a->id, 'filename' => $a->filename, 'kb' => (int) round($a->size / 1024)]),
            ]),
        ]);
    }

    public function reply(Request $request, Store $store, int $ticket): RedirectResponse
    {
        $ticket = $this->find($store, $ticket);
        $validator = Validator::make($request->all(), ['body' => ['required', 'string', 'max:10000']] + SupportDesk::ATTACHMENT_RULES);
        $validator->validate();
        $this->desk->reply($ticket, 'merchant', $request->input('body'), (array) $request->file('attachments', []), false, $request->attributes->get('storeUser'));

        return redirect()->to(app_route('app.support.show', ['ticket' => $ticket->id, 'notice' => 'reply_sent']));
    }

    public function close(Request $request, Store $store, int $ticket): RedirectResponse
    {
        $ticket = $this->find($store, $ticket);
        $ticket->forceFill(['status' => $ticket->status === 'closed' ? 'open' : 'closed', 'resolved_at' => $ticket->status === 'closed' ? null : ($ticket->resolved_at ?? now())])->save();

        return redirect()->to(app_route('app.support.show', ['ticket' => $ticket->id, 'notice' => 'saved']));
    }

    public function attachment(Request $request, Store $store, int $attachment): Response
    {
        $file = Attachment::with('message.ticket')->findOrFail($attachment);
        abort_unless($file->message->ticket->store_id === $store->id && ! $file->message->internal, 404);

        return self::download($file);
    }

    public static function download(Attachment $file): Response
    {
        $raw = $file->getRawOriginal('content');
        $content = base64_decode(is_resource($raw) ? stream_get_contents($raw) : (string) $raw);

        return response($content, 200, [
            'Content-Type' => $file->mime,
            'Content-Disposition' => (str_starts_with($file->mime, 'image/') || $file->mime === 'application/pdf' ? 'inline' : 'attachment').'; filename="'.addslashes($file->filename).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function find(Store $store, int $id): Ticket
    {
        return Ticket::where('store_id', $store->id)->findOrFail($id);
    }

    /**
     * A custom plan request (G34): the modules and limits the owner needs, as a support ticket the
     * team answers from Internal Admin with the store's access panel next to it.
     */
    private function customPlan(Request $request, Store $store): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'modules' => ['required', 'array', 'min:1'], 'modules.*' => ['string', 'in:'.implode(',', array_keys(\App\Support\Modules::ALL))],
            'widgets' => ['nullable', 'integer', 'min:0', 'max:100000'], 'runs' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'note' => ['nullable', 'string', 'max:2000'],
        ], ['modules.required' => 'Tick the modules you need.'])->validate();

        $labels = array_map(fn ($k) => \App\Support\Modules::label($k), $data['modules']);
        $closest = collect(\App\Support\Plans::public())->sortBy('price')->first(fn ($p) => ! array_diff(array_diff($data['modules'], \App\Support\Modules::COMING), $p['includes'] ?? [])
            && (($p['limits']['active_experiences'] ?? null) === null || ($data['widgets'] ?? 0) <= $p['limits']['active_experiences'])
            && (($p['limits']['automation_executions'] ?? null) === null || ($data['runs'] ?? 0) <= $p['limits']['automation_executions']));
        $body = "Custom plan request\n\nModules: ".implode(', ', $labels)
            .(isset($data['widgets']) ? "\nLive widgets: {$data['widgets']}" : '').(isset($data['runs']) ? "\nAutomation runs a month: {$data['runs']}" : '')
            .(isset($data['budget']) ? "\nBudget: \${$data['budget']} a month" : '').(($data['note'] ?? '') !== '' ? "\n\n".$data['note'] : '');

        $ticket = $this->desk->open($store, $request->attributes->get('storeUser'), ['subject' => 'Custom plan request', 'body' => $body, 'category' => 'custom_plan', 'priority' => 'normal']);
        $ticket->forceFill(['category' => 'custom_plan', 'diagnostics' => ($ticket->diagnostics ?? []) + ['custom_plan' => [
            'modules' => $data['modules'], 'widgets' => $data['widgets'] ?? null, 'runs' => $data['runs'] ?? null, 'budget' => $data['budget'] ?? null,
            'closest' => $closest['name'] ?? null, 'plan' => $store->effectivePlan(),
        ]]])->save();

        return redirect()->to(app_route('app.support.show', ['ticket' => $ticket->id, 'notice' => 'ticket_created']));
    }
}
