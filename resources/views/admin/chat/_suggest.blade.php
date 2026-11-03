@if ($suggestions)
    <span class="ad-muted">Suggested:</span>
    @foreach ($suggestions as $s)
        <button type="button" class="ad-btn small" data-suggest="{{ $s['body'] }}" data-id="{{ $s['saved_reply_id'] }}" title="{{ $s['source'] }}@if ($s['missing']) · Fill in: {{ implode(', ', $s['missing']) }}@endif">{{ \Illuminate\Support\Str::limit($s['title'], 40) }}</button>
    @endforeach
@endif
