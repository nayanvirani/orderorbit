@extends('admin.layout')
@section('title', 'Workflow failures')
@section('content')
<div class="ad-head"><div><h1>Workflow failures</h1><p>Automation runs that failed after their retries, across every store.</p></div></div>
<section class="ad-card">
    <h2>Most common errors, 7 days</h2>
    @forelse ($byError as $e)<p><b>{{ $e->n }}×</b> <span class="ad-bad">{{ \Illuminate\Support\Str::limit($e->error, 200) }}</span></p>@empty<p class="ad-muted">No failures this week.</p>@endforelse
</section>
<section class="ad-card flush"><div class="ad-scroll">
<table class="ad-table">
    <thead><tr><th>Run</th><th>Store</th><th>Workflow</th><th>Error</th><th>Attempts</th><th>Failed</th></tr></thead>
    <tbody>
        @foreach ($runs as $r)
            <tr><td>#{{ $r->id }}</td><td><a href="{{ route('admin.store', $r->store_id) }}">{{ $r->store?->shop_domain }}</a></td><td>{{ $r->workflow?->name ?? '—' }}</td><td class="ad-bad">{{ \Illuminate\Support\Str::limit($r->error, 140) }}</td><td>{{ $r->attempts }}</td><td>{{ $r->updated_at->diffForHumans() }}</td></tr>
        @endforeach
    </tbody>
</table>
</div></section>
{{ $runs->links('admin._pager') }}
@endsection
