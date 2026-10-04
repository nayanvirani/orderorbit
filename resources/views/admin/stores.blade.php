@extends('admin.layout')
@section('title', 'Stores')
@section('content')
<h1>Stores</h1>
<form method="GET" class="ad-filters">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Shop domain or name">
    <select name="status"><option value="">All</option><option value="installed" @selected(request('status') === 'installed')>Installed</option><option value="uninstalled" @selected(request('status') === 'uninstalled')>Uninstalled</option></select>
    <button class="ad-btn" type="submit">Filter</button>
</form>
<table class="ad-table">
    <thead><tr><th>Store</th><th>Status</th><th>Plan</th><th>Shopify plan</th><th>Live experiences</th><th>Events, 7 d</th><th>Pixel</th><th>Installed</th></tr></thead>
    <tbody>
        @foreach ($stores as $s)
            <tr>
                <td><a href="{{ route('admin.store', $s->id) }}">{{ $s->name ?? '—' }}</a><br><span class="ad-muted">{{ $s->shop_domain }}</span></td>
                <td>{!! $s->isInstalled() ? '<span class="ad-ok">Installed</span>' : '<span class="ad-bad">Uninstalled</span>' !!}</td>
                <td>{{ $s->effectivePlan() ?? '—' }}{{ $s->offersSuspended() ? ' · suspended' : '' }}</td>
                <td>{{ $s->shopify_plan ?? '—' }}</td>
                <td>{{ $live[$s->id] ?? 0 }}</td>
                <td>{{ number_format($events[$s->id] ?? 0) }}</td>
                <td>{!! $s->web_pixel_id ? '<span class="ad-ok">On</span>' : '<span class="ad-bad">Off</span>' !!}</td>
                <td>{{ $s->installed_at?->toFormattedDateString() ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
{{ $stores->links() }}
@endsection
