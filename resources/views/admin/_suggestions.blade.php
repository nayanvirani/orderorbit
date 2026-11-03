{{-- "Suggest reply" (G35): $suggestUrl opens the suggestions; $suggestions fills the reply box below it. --}}
<div class="ad-suggest" id="suggest">
    <a class="ad-btn" href="{{ $suggestUrl }}#suggest">{{ isset($suggestions) ? 'More suggestions' : 'Suggest reply' }}</a>
    <span class="ad-muted">From saved replies and help articles, filled with this store's details. No AI, no cost. <a href="{{ route('admin.replies') }}">Manage saved replies</a></span>
    @isset($suggestions)
        @forelse ($suggestions as $s)
            <div class="ad-card" style="margin-top:10px;padding:12px 14px">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><b>{{ $s['title'] }}</b>
                    <button type="button" class="ad-btn small" data-body="{{ $s['body'] }}" data-id="{{ $s['saved_reply_id'] }}" onclick="var f=this.closest('section').querySelector('form textarea[name=body]');f.value=this.dataset.body;var h=this.closest('section').querySelector('input[name=saved_reply_id]');if(h)h.value=this.dataset.id||'';f.focus()">Use this reply</button></div>
                <div class="ad-muted" style="margin:4px 0">{{ $s['source'] }}</div>
                @if ($s['missing'])<div class="ad-flash bad" style="margin:6px 0">Fill in before sending: {{ implode(', ', $s['missing']) }}</div>@endif
                <div style="white-space:pre-wrap;font-size:13px">{{ \Illuminate\Support\Str::limit($s['body'], 420) }}</div>
            </div>
        @empty
            <p class="ad-muted">No matching saved reply or help article. Write the reply, then save it under Saved replies for next time.</p>
        @endforelse
    @endisset
</div>
