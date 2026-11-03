@extends('admin.layout')
@section('title', 'Saved replies')
@section('content')
<div class="ad-head"><div><h1>Saved replies</h1><p>Answers "Suggest reply" can pick from on tickets and contact messages, alongside the help centre. Placeholders: @foreach (\App\Support\ReplySuggester::PLACEHOLDERS as $p)<code>{{ str_repeat('{', 2).$p.str_repeat('}', 2) }}</code> @endforeach</p></div></div>
<div class="ad-grid-2">
    <section class="ad-card flush"><div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>Reply</th><th>Used</th><th></th></tr></thead>
            <tbody>
                @forelse ($replies as $r)
                    <tr><td><a href="{{ route('admin.replies.edit', $r->id) }}"><b>{{ $r->title }}</b></a><br><span class="ad-muted">{{ \Illuminate\Support\Str::limit($r->body, 90) }}</span></td><td>{{ $r->use_count }}</td>
                        <td><form method="POST" action="{{ route('admin.replies.delete', $r->id) }}" onsubmit="return confirm('Delete this saved reply?')">@csrf<button class="ad-btn small danger" type="submit">Delete</button></form></td></tr>
                @empty <tr><td colspan="3" class="ad-muted">No saved replies yet.</td></tr> @endforelse
            </tbody>
        </table>
    </div></section>
    <section class="ad-card">
        <h2>{{ $edit ? 'Edit "'.$edit->title.'"' : 'Add a saved reply' }}</h2>
        <form method="POST" action="{{ $edit ? route('admin.replies.update', $edit->id) : route('admin.replies.store') }}" class="ad-form">
            @csrf
            <label>Title<input name="title" required maxlength="160" value="{{ old('title', $edit?->title) }}" placeholder="Bundle discount missing at checkout"></label>
            <label>Keywords (help it match)<input name="keywords" maxlength="500" value="{{ old('keywords', $edit?->keywords) }}" placeholder="bundle discount checkout variant"></label>
            <label>Reply<textarea name="body" rows="10" required>{{ old('body', $edit?->body) }}</textarea></label>
            <button class="ad-btn primary" type="submit">Save</button>
            @if ($edit)<a class="ad-btn" href="{{ route('admin.replies') }}">Cancel</a>@endif
        </form>
    </section>
</div>
@endsection
