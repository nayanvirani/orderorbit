@extends('admin.layout')
@section('title', 'Store questions')
@section('content')
<div class="ad-head"><div><h1>Store questions</h1><p>{{ number_format($builtIn) }} built-in questions, plus the ones added here. Each answers from the store's own data, with the same numbers as the dashboards; no AI.</p></div></div>

<section class="ad-card">
    <h2>Add a question</h2>
    <form method="POST" action="{{ route('admin.questions.store') }}" class="ad-form">
        @csrf
        <label>Wording shown to merchants<input name="text" maxlength="200" required value="{{ old('text') }}" placeholder="How much did my top products sell last weekend?"></label>
        <label>Topic<select name="topic">@foreach ($topics as $t)<option @selected(old('topic') === $t)>{{ $t }}</option>@endforeach</select></label>
        <label>Answer with
            <select name="kind">
                <option value="value">A number</option><option value="compare">A number compared with the period before</option>
                <option value="rank">A ranking (top 10)</option><option value="offers">Growvia offers</option>
            </select>
        </label>
        <label>Number (for a number or comparison)<select name="measure"><option value="">—</option>@foreach ($measures as $k => $m)<option value="{{ $k }}">{{ ucfirst($m[0]) }}</option>@endforeach</select></label>
        <label>Ranking<select name="ranking"><option value="">—</option>@foreach ($rankings as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
        <label>Offers<select name="offers"><option value="">—</option><option value="total">Total added by offers</option><option value="top">Offers that earned the most</option></select></label>
        <label>Period<select name="period">@foreach ($periods as $k => $p)<option value="{{ $k }}">{{ ucfirst($p) }}</option>@endforeach</select></label>
        <div><button class="ad-btn primary" type="submit">Add question</button></div>
    </form>
</section>

<section class="ad-card flush">
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>Question</th><th>Topic</th><th>Answer</th><th>Added</th><th></th></tr></thead>
            <tbody>
                @forelse ($questions as $q)
                    <tr>
                        <td>{{ $q->text }}</td><td>{{ $q->topic }}</td>
                        <td class="ad-muted">{{ $q->spec['kind'] }} · {{ $q->spec['measure'] ?? $q->spec['dim'] ?? $q->spec['mode'] ?? '' }} · {{ $periods[$q->spec['period']] ?? '' }}</td>
                        <td>{{ $q->created_at->diffForHumans() }}<div class="ad-muted ad-small">{{ $q->created_by }}</div></td>
                        <td><form method="POST" action="{{ route('admin.questions.toggle', $q) }}">@csrf<button class="ad-btn" type="submit">{{ $q->active ? 'Hide' : 'Show' }}</button></form></td>
                    </tr>
                @empty <tr><td colspan="5" class="ad-empty">No added questions yet.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
