@extends('admin.layout')
@section('title', 'Live chat')
@section('content')
@php($online = $me->chat_status === 'online')
<div class="ad-head">
    <div><h1>Live chat</h1><p>Chats from store owners inside Growvia, with the store's details beside each one. Every chat is saved to the store's support history.</p></div>
    <form method="POST" action="{{ route('admin.chat.status') }}" class="ad-chat-presence">@csrf
        <button class="ad-btn {{ $online ? 'primary' : '' }}" name="status" value="online" type="submit" id="go-online"><span class="ad-chat-dot {{ $online ? 'on' : '' }}"></span>{{ $online ? "You're online" : 'Go online' }}</button>
        <button class="ad-btn {{ $online ? '' : 'primary' }}" name="status" value="away" type="submit">Away</button>
    </form>
</div>
@unless ($online)<div class="ad-note warn">You're away. Store owners see "We'll be with you soon" and get replies by email until someone on the team is online. Keep this page open while you're online.</div>@endunless

<div id="chat-live" data-feed="{{ route('admin.chat.feed', array_filter(['tab' => $tab, 'chat' => $current?->id])) }}" @if ($current) data-typing="{{ route('admin.chat.typing', $current->id) }}" @endif>
    <div id="chat-stats">@include('admin.chat._stats')</div>

    <div class="ad-chat">
        <section class="ad-card flush ad-chat-queue">
            <div id="chat-queue">@include('admin.chat._queue')</div>
        </section>

        <section class="ad-card ad-chat-main">
            @if ($current)
                <div class="ad-chat-top">
                    <div id="chat-head">@include('admin.chat._head')</div>
                    @if ($current->chat_state !== 'closed')
                        <div class="ad-chat-tools">
                            <form method="POST" action="{{ route('admin.chat.assign', $current->id) }}">@csrf
                                <select name="assigned_to" onchange="this.form.submit()" aria-label="Assign"><option value="">Unassigned</option>@foreach ($team as $u)<option value="{{ $u->id }}" @selected($current->assigned_to === $u->id)>{{ $u->name ?: $u->email }}</option>@endforeach</select>
                            </form>
                            <form method="POST" action="{{ route('admin.chat.ticket', $current->id) }}" onsubmit="return confirm('Turn this chat into a ticket? The store owner follows it under Support.')">@csrf<button class="ad-btn small" type="submit">Make a ticket</button></form>
                            <form method="POST" action="{{ route('admin.chat.close', $current->id) }}" onsubmit="return confirm('Close this chat? The store owner gets a copy by email.')">@csrf<button class="ad-btn small danger" type="submit">Close chat</button></form>
                        </div>
                    @endif
                </div>
                <div id="chat-thread" class="ad-chat-thread">@include('admin.chat._thread')</div>
                @if ($current->chat_state !== 'closed')
                    <div id="chat-suggest" class="ad-chat-suggest">@include('admin.chat._suggest')</div>
                    <form id="chat-compose" method="POST" action="{{ route('admin.chat.send', $current->id) }}" enctype="multipart/form-data" class="ad-chat-compose">
                        @csrf
                        <input type="hidden" name="saved_reply_id" value="">
                        <textarea name="body" rows="3" required placeholder="Reply… (Enter sends, Shift+Enter for a new line)" aria-label="Reply"></textarea>
                        <div class="ad-chat-row">
                            <label class="ad-check"><input type="checkbox" name="internal" value="1"> Internal note (store owner can't see it)</label>
                            <span style="display:flex;gap:10px;align-items:center">
                                <label class="ad-btn small" style="cursor:pointer">📎 Attach<input type="file" name="attachments[]" multiple hidden></label>
                                <button class="ad-btn primary" type="submit">Send</button>
                            </span>
                        </div>
                        <p class="ad-muted" style="margin:0" id="chat-files"></p>
                        <p class="ad-muted" style="margin:0">Suggestions come from saved replies, help articles and this store's details. No AI, no cost.@if ($current->chat_state === 'offline' || ! $current->merchant_seen_at?->gt(now()->subMinutes(2))) The store owner isn't in the app now, so your reply also goes by email.@endif</p>
                    </form>
                @endif
            @else
                <div class="ad-empty" style="padding:48px 16px;text-align:center"><b>Pick a chat from the queue.</b><p class="ad-muted">New chats appear on the left within a few seconds, with a browser notification if you allow it.</p></div>
            @endif
        </section>

        <aside class="ad-card ad-chat-side">
            @if ($current)
                <h2>Store details</h2>
                <dl class="ad-chat-details">@foreach ($details as $k => $v)<div><dt>{{ $k }}</dt><dd>{{ $v }}</dd></div>@endforeach</dl>
                <p><a href="{{ route('admin.store', $current->store_id) }}">Open store in Internal Admin</a></p>
                <h2 style="margin-top:18px">History</h2>
                @forelse ($history as $h)
                    <p style="margin:6px 0"><a href="{{ $h->channel === 'chat' ? route('admin.chat', ['tab' => 'closed', 'chat' => $h->id]) : route('admin.ticket', $h->id) }}">{{ $h->channel === 'chat' ? 'Chat' : 'Ticket '.$h->reference() }}</a> · {{ \Illuminate\Support\Str::limit(preg_replace('/^Chat: /', '', $h->subject), 50) }}<br><span class="ad-muted">{{ $h->created_at->format('j M Y') }} · {{ \App\Models\Support\Ticket::STATUSES[$h->status] ?? $h->status }}</span></p>
                @empty <p class="ad-muted">No earlier chats or tickets.</p> @endforelse
            @else
                <h2>Chat hours</h2>
                <p class="ad-muted">Now: {{ $hours }}. Outside these hours store owners leave a message and get your reply by email.</p>
                <form method="POST" action="{{ route('admin.chat.hours') }}" class="ad-form">
                    @csrf
                    <div style="display:flex;flex-wrap:wrap;gap:8px">@foreach (\App\Services\Support\LiveChat::DAYS as $n => $d)<label class="ad-check"><input type="checkbox" name="days[]" value="{{ $n }}" @checked(in_array($n, $settings['days']))> {{ $d }}</label>@endforeach</div>
                    <div style="display:flex;gap:10px"><label>From<input type="time" name="from" value="{{ $settings['from'] }}" required></label><label>To<input type="time" name="to" value="{{ $settings['to'] }}" required></label></div>
                    <label>Time zone<input name="timezone" value="{{ $settings['timezone'] }}" required><small>For example Asia/Kolkata.</small></label>
                    <button class="ad-btn primary" type="submit">Save hours</button>
                </form>
                <p class="ad-muted" style="margin-top:14px">Store owners see the chat button once the <a href="{{ route('admin.flags', ['key' => 'live_chat']) }}">live chat flag</a> is on for their store.</p>
            @endif
        </aside>
    </div>
</div>

<script>
(() => {
    const live = document.getElementById('chat-live');
    const thread = document.getElementById('chat-thread');
    const form = document.getElementById('chat-compose');
    const token = document.querySelector('input[name=_token]').value;
    let known = null;
    const nearBottom = () => !thread || thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
    const toBottom = () => { if (thread) thread.scrollTop = thread.scrollHeight; };
    const swap = (id, html) => { const el = document.getElementById(id); if (el && html !== null && html !== undefined && el.innerHTML.trim() !== html.trim()) { el.innerHTML = html; return true; } return false; };
    toBottom();

    async function tick() {
        try {
            const res = await fetch(live.dataset.feed, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const d = await res.json();
            const stay = nearBottom();
            swap('chat-stats', d.stats); swap('chat-queue', d.queue); swap('chat-head', d.head); swap('chat-suggest', d.suggest);
            if (swap('chat-thread', d.thread) && stay) toBottom();
            if (known) {
                const fresh = d.waiting.filter((id) => !known.includes(id));
                if (fresh.length) {
                    document.title = '(' + d.waiting.length + ') New chat · Growvia Admin';
                    if (window.Notification && Notification.permission === 'granted') new Notification('New live chat', { body: 'A store owner is waiting for a reply.' });
                }
            }
            known = d.waiting;
        } catch (e) {}
    }
    setInterval(() => { if (!document.hidden || Date.now() % 15000 < 3000) tick(); }, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) document.title = 'Live chat · Growvia Admin'; });
    document.getElementById('go-online')?.addEventListener('click', () => { if (window.Notification && Notification.permission === 'default') Notification.requestPermission(); });

    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-suggest]');
        if (!b || !form) return;
        form.body.value = b.dataset.suggest;
        form.saved_reply_id.value = b.dataset.id || '';
        form.body.focus();
    });

    if (!form) return;
    let typed = 0;
    form.body.addEventListener('input', () => {
        if (Date.now() - typed < 3000) return;
        typed = Date.now();
        fetch(live.dataset.typing, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } }).catch(() => {});
    });
    form.body.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    form.querySelector('input[type=file]').addEventListener('change', (e) => { document.getElementById('chat-files').textContent = Array.from(e.target.files).map((f) => '📎 ' + f.name).join(', '); });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.body.value.trim()) return;
        const button = form.querySelector('button[type=submit]');
        button.disabled = true;
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            if (!res.ok) { const d = await res.json().catch(() => ({})); alert(d.message || 'The reply wasn\'t sent. Try again.'); return; }
            form.reset();
            document.getElementById('chat-files').textContent = '';
            await tick();
            toBottom();
        } finally { button.disabled = false; }
    });
})();
</script>
@endsection
