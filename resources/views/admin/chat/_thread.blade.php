@foreach ($current->messages as $m)
    <div @class(['ad-chat-msg', $m->author, 'internal' => $m->internal])>
        <div class="ad-chat-bubble">{{ $m->body }}
            @foreach ($m->attachments as $a)<a href="{{ route('admin.attachment', $a->id) }}" target="_blank">📎 {{ $a->filename }} ({{ round($a->size / 1024) }} KB)</a>@endforeach
        </div>
        <small>{{ $m->internal ? 'Internal note · ' : '' }}{{ $m->author === 'team' ? ($m->author_name ?: 'Team') : ($current->requester?->first_name ?: 'Store owner') }} · {{ $m->created_at->format('H:i') }}</small>
    </div>
@endforeach
@if ($current->merchant_typing_at?->gt(now()->subSeconds(6)))<p class="ad-muted" style="margin:0">{{ $current->requester?->first_name ?: 'The store owner' }} is typing…</p>@endif
