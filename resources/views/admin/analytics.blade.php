@extends('admin.layout')
@section('title', 'Analytics processing')
@section('content')
<h1>Analytics processing</h1>
<div class="ad-kpis">
    <div><small>Events, last hour</small><b>{{ number_format($lastHour) }}</b><span>Pixel collector</span></div>
    <div><small>Events stored</small><b>{{ number_format($total) }}</b><span>Oldest {{ $oldest ? \Illuminate\Support\Carbon::parse($oldest)->toFormattedDateString() : '—' }}</span></div>
    <div><small>Pixels connected</small><b>{{ $pixels['connected'] }} / {{ $pixels['installed'] }}</b><span>Installed stores</span></div>
</div>
<div class="ad-grid">
    <section class="ad-card">
        <h2>Events per day, 14 days</h2>
        @php($max = max(1, $byDay->max() ?? 1))
        <div class="ad-bars">@foreach ($byDay as $day => $n)<span title="{{ $day }}: {{ $n }}"><i style="height:{{ $n / $max * 100 }}%"></i></span>@endforeach</div>
    </section>
    <section class="ad-card">
        <h2>Busiest stores, 24 h</h2>
        @forelse ($topStores as $row)<p><a href="{{ route('admin.store', $row->store_id) }}">{{ $row->store?->shop_domain }}</a> · {{ number_format($row->n) }}</p>@empty<p class="ad-muted">No events.</p>@endforelse
    </section>
</div>
<section class="ad-card">
    <h2>Events by name, 24 h</h2>
    <table class="ad-table"><tbody>@forelse ($byName as $name => $n)<tr><td><code>{{ $name ?? '(unnamed)' }}</code></td><td>{{ number_format($n) }}</td></tr>@empty<tr><td class="ad-muted">None</td></tr>@endforelse</tbody></table>
    <p class="ad-muted">Retention: events older than 13 months (or the store's Privacy setting) are pruned nightly at 03:15 UTC.</p>
</section>
@endsection
