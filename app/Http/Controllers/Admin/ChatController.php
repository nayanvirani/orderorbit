<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Support\Ticket;
use App\Models\User;
use App\Services\Support\LiveChat;
use App\Services\Support\SupportDesk;
use App\Support\ReplySuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Internal Admin › Live chat (G43): the queue, the conversation with the store's details beside
 * it, and business hours. The page checks for changes every few seconds (feed), which also keeps
 * the team member marked online.
 */
class ChatController extends Controller
{
    public const TABS = ['waiting' => 'Waiting', 'active' => 'Active', 'closed' => 'Closed'];

    public function __construct(private readonly LiveChat $chat) {}

    public function index(Request $request): View
    {
        $this->checkIn($request->user());

        return view('admin.chat.index', $this->data($request) + [
            'me' => $request->user(), 'settings' => LiveChat::settings(), 'hours' => LiveChat::hoursText(),
            'team' => User::where('is_admin', true)->whereNull('disabled_at')->orderBy('name')->get(),
        ]);
    }

    /** What changed since the page loaded: queue, stats and the open conversation, as HTML. */
    public function feed(Request $request): JsonResponse
    {
        $this->checkIn($request->user());
        $data = $this->data($request);

        return response()->json([
            'stats' => view('admin.chat._stats', $data)->render(),
            'queue' => view('admin.chat._queue', $data)->render(),
            'head' => $data['current'] ? view('admin.chat._head', $data)->render() : null,
            'thread' => $data['current'] ? view('admin.chat._thread', $data)->render() : null,
            'suggest' => $data['current'] ? view('admin.chat._suggest', $data)->render() : null,
            'waiting' => Ticket::where('channel', 'chat')->where('chat_state', 'waiting')->pluck('id'),
        ]);
    }

    public function send(Request $request, int $chat): JsonResponse
    {
        $ticket = $this->find($chat);
        $request->validate(['body' => ['required', 'string', 'max:10000']] + SupportDesk::ATTACHMENT_RULES);
        $this->chat->fromTeam($ticket, $request->user(), $request->input('body'), (array) $request->file('attachments', []), $request->boolean('internal'));
        if (! $request->boolean('internal')) {
            SavedReplyController::used($request->input('saved_reply_id'));
        }

        return response()->json(['ok' => true]);
    }

    public function typing(int $chat): JsonResponse
    {
        $this->chat->typing($this->find($chat), 'team');

        return response()->json(['ok' => true]);
    }

    public function assign(Request $request, int $chat): RedirectResponse
    {
        $ticket = $this->find($chat);
        $ticket->forceFill(['assigned_to' => User::where('is_admin', true)->whereKey($request->integer('assigned_to'))->value('id')])->save();

        return back()->with('status', 'Chat assigned.');
    }

    public function close(int $chat): RedirectResponse
    {
        $this->chat->close($this->find($chat));

        return redirect()->route('admin.chat', ['tab' => 'closed', 'chat' => $chat])->with('status', 'Chat closed. The store owner gets a copy by email.');
    }

    public function makeTicket(int $chat): RedirectResponse
    {
        $this->chat->makeTicket($this->find($chat));

        return redirect()->route('admin.ticket', $chat)->with('status', 'The chat is now a ticket.');
    }

    public function status(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['chat_status' => $request->input('status') === 'online' ? 'online' : 'away', 'chat_seen_at' => now()])->save();

        return back();
    }

    public function hours(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['array'], 'days.*' => ['integer', 'between:1,7'],
            'from' => ['required', 'date_format:H:i'], 'to' => ['required', 'date_format:H:i', 'after:from'], 'timezone' => ['required', 'timezone'],
        ]);
        LiveChat::saveSettings(['days' => array_values(array_map('intval', $data['days'] ?? [])), 'from' => $data['from'], 'to' => $data['to'], 'timezone' => $data['timezone']]);

        return back()->with('status', 'Chat hours saved.');
    }

    private function data(Request $request): array
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'waiting';
        $chats = fn (string $state) => Ticket::with('store', 'assignee')->where('channel', 'chat')
            ->when($state === 'waiting', fn ($q) => $q->whereIn('chat_state', ['waiting', 'offline'])->orderBy('id'))
            ->when($state === 'active', fn ($q) => $q->where('chat_state', 'active')->latest('last_reply_at'))
            ->when($state === 'closed', fn ($q) => $q->where('chat_state', 'closed')->latest('closed_at')->limit(30));
        $current = $request->integer('chat') ? Ticket::with(['store', 'requester', 'assignee', 'messages.attachments'])->where('channel', 'chat')->find($request->integer('chat')) : null;

        return [
            'tab' => $tab, 'tabs' => self::TABS, 'queue' => $chats($tab)->get(),
            'counts' => collect(self::TABS)->map(fn ($l, $k) => $k === 'closed' ? null : $chats($k)->count()),
            'stats' => $this->stats(), 'current' => $current,
            'suggestions' => $current ? $this->suggestions($current) : [],
            'details' => $current ? $this->details($current) : [],
            'history' => $current ? Ticket::where('store_id', $current->store_id)->whereKeyNot($current->id)->latest('id')->limit(8)->get() : collect(),
        ];
    }

    private function stats(): array
    {
        $waiting = Ticket::where('channel', 'chat')->where('chat_state', 'waiting')->oldest('id')->get(['created_at']);
        $today = Ticket::where('channel', 'chat')->where('created_at', '>=', now()->startOfDay());
        $answered = (clone $today)->whereNotNull('first_reply_at')->get(['created_at', 'first_reply_at']);
        $rated = Ticket::where('channel', 'chat')->whereNotNull('rating')->where('created_at', '>=', now()->subDays(30))->pluck('rating');

        return [
            ['Waiting now', (string) $waiting->count(), $waiting->isEmpty() ? 'Nobody waiting' : 'Longest '.$waiting->first()->created_at->diffForHumans(null, true)],
            ['Active', (string) Ticket::where('channel', 'chat')->where('chat_state', 'active')->count(), $this->chat->teamOnline().' of the team online'],
            ['Chats today', (string) (clone $today)->count(), 'Since midnight'],
            ['First reply', $answered->isEmpty() ? '—' : self::duration((int) $answered->avg(fn ($t) => $t->created_at->diffInSeconds($t->first_reply_at))), 'Average today'],
            ['Rating', $rated->isEmpty() ? '—' : round($rated->filter(fn ($r) => $r > 0)->count() / $rated->count() * 100).'%', 'Good, last 30 days'],
        ];
    }

    private function suggestions(Ticket $chat): array
    {
        $last = $chat->messages->where('author', 'merchant')->last();

        return $last ? app(ReplySuggester::class)->suggest($last->body, ReplySuggester::context($chat->requester?->first_name, $chat->store), 3) : [];
    }

    private function details(Ticket $chat): array
    {
        $store = $chat->store;
        $tz = $store->timezone ?: 'UTC';

        return array_filter([
            'Shop' => $store->shop_domain,
            'Growvia plan' => ucfirst($store->effectivePlan()),
            'Shopify plan' => $store->shopify_plan,
            'Installed' => $store->installed_at?->format('j M Y'),
            'Live offers' => $store->experiences()->where('status', 'published')->count().' of '.$store->experiences()->count(),
            'Theme' => $store->theme_name,
            'Pixel' => $store->web_pixel_id ? 'Connected' : 'Not connected',
            'Missing permissions' => implode(', ', $store->missingScopes()) ?: null,
            'Failed workflow runs (7 days)' => (string) \App\Models\Automation\WorkflowRun::where('store_id', $store->id)->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
            'Their local time' => now()->setTimezone($tz)->format('H:i, D').' ('.$tz.')',
        ], fn ($v) => $v !== null && $v !== '');
    }

    public static function duration(int $seconds): string
    {
        return $seconds < 60 ? $seconds.' s' : intdiv($seconds, 60).' min'.($seconds % 60 ? ' '.($seconds % 60).' s' : '');
    }

    private function checkIn(User $user): void
    {
        $user->forceFill(['chat_seen_at' => now()])->saveQuietly();
    }

    private function find(int $id): Ticket
    {
        return Ticket::where('channel', 'chat')->findOrFail($id);
    }
}
