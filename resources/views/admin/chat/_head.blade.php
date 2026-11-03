<h2 style="margin:0">{{ $current->store->shop_domain }}</h2>
<p class="ad-muted" style="margin:2px 0 0">
    {{ $current->requester?->displayName() ?? 'A staff member' }}@if ($current->page) · on <code>{{ $current->page }}</code>@endif
    · {{ \App\Services\Support\LiveChat::TOPICS[$current->category] ?? \App\Models\Support\Ticket::CATEGORIES[$current->category] ?? $current->category }}
    · @if ($current->first_reply_at)waited {{ \App\Http\Controllers\Admin\ChatController::duration((int) $current->created_at->diffInSeconds($current->first_reply_at)) }}@else<b style="color:var(--bad)">waiting {{ $current->created_at->diffForHumans(null, true) }}</b>@endif
    @if ($current->chat_state === 'closed') · <span class="ad-badge">Closed</span>@if ($current->rating) {{ $current->rating > 0 ? '👍 Good' : '👎 Not good' }}@endif @endif
</p>
