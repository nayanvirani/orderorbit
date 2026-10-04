@extends('admin.layout')
@section('title', 'Audit log')
@section('content')
<h1>Audit log</h1>
<form method="GET" class="ad-filters">
    <input name="action" value="{{ request('action') }}" placeholder="Action starts with, e.g. experience.">
    <input name="store" value="{{ request('store') }}" placeholder="Shop domain">
    <button class="ad-btn" type="submit">Filter</button>
</form>
<table class="ad-table">
    <thead><tr><th>When</th><th>Store</th><th>Action</th><th>Actor</th><th>Details</th></tr></thead>
    <tbody>
        @foreach ($logs as $l)
            <tr><td>{{ $l->created_at->toDateTimeString() }}</td><td>{{ $l->store?->shop_domain ?? 'Platform' }}</td><td><code>{{ $l->action }}</code></td><td>{{ $l->actor_type }}{{ $l->actor_id ? ' #'.$l->actor_id : '' }}</td><td class="ad-muted">{{ \Illuminate\Support\Str::limit(json_encode($l->context ?? []), 160) }}</td></tr>
        @endforeach
    </tbody>
</table>
{{ $logs->links() }}
@endsection
