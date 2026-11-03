<nav class="ad-tabs" style="margin:0;padding:0 8px">
    @foreach ($tabs as $k => $l)
        <a href="{{ route('admin.chat', ['tab' => $k]) }}" @if ($tab === $k) aria-current="page" @endif>{{ $l }}@if ($counts[$k] !== null)<span>{{ $counts[$k] }}</span>@endif</a>
    @endforeach
</nav>
@forelse ($queue as $q)
    @php($since = $q->chat_state === 'closed' ? $q->closed_at : ($q->first_reply_at ? $q->last_reply_at : $q->created_at))
    @php($late = in_array($q->chat_state, ['waiting', 'offline'], true) && ! $q->first_reply_at && $q->created_at->lt(now()->subMinutes(3)))
    <a class="ad-chat-item" href="{{ route('admin.chat', ['tab' => $tab, 'chat' => $q->id]) }}" @if ($current?->id === $q->id) aria-current="true" @endif>
        <span class="ad-chat-row"><b>{{ $q->store->shop_domain }}</b><span @class(['ad-chat-wait', 'late' => $late])>{{ $since?->diffForHumans(null, true, true) }}</span></span>
        <span class="ad-chat-preview">{{ \Illuminate\Support\Str::limit(preg_replace('/^Chat: /', '', $q->subject), 70) }}</span>
        <span class="ad-muted">
            @if ($q->chat_state === 'offline')Outside hours · reply goes by email @elseif ($q->chat_state === 'waiting')Waiting for a reply @else{{ $q->assignee?->name ?? 'Unassigned' }}@endif
            @if ($q->rating) · {{ $q->rating > 0 ? '👍' : '👎' }}@endif
        </span>
    </a>
@empty
    <p class="ad-muted" style="padding:16px">{{ $tab === 'waiting' ? 'Nobody is waiting.' : ($tab === 'active' ? 'No active chats.' : 'No closed chats yet.') }}</p>
@endforelse
