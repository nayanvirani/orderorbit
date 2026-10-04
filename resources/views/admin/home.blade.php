@extends('admin.layout')
@section('title', 'Overview')
@section('content')
<h1>Overview</h1>
<div class="ad-kpis">
    <div><small>Installed stores</small><b>{{ number_format($kpis['installed']) }}</b><span>{{ $kpis['uninstalled'] }} uninstalled</span></div>
    <div><small>Estimated MRR</small><b>${{ number_format($kpis['mrr'], 2) }}</b><span>{{ $kpis['by_plan']->map(fn ($n, $p) => ucfirst($p).' '.$n)->implode(' · ') ?: 'No plans yet' }}</span></div>
    <div><small>Events, 24 h</small><b>{{ number_format($kpis['events_24h']) }}</b><span><a href="{{ route('admin.analytics') }}">Analytics processing</a></span></div>
    <div><small>Failed workflow runs, 24 h</small><b>{{ $kpis['failed_runs_24h'] }}</b><span><a href="{{ route('admin.failures') }}">View failures</a></span></div>
    <div><small>Open tickets</small><b>{{ $kpis['open_tickets'] }}</b><span><a href="{{ route('admin.tickets') }}">Support desk</a></span></div>
    <div><small>Unprocessed webhooks, 24 h</small><b>{{ $kpis['webhooks_unprocessed'] }}</b><span>Should stay at 0</span></div>
</div>
<div class="ad-grid">
    <section class="ad-card">
        <h2>Tickets waiting for us</h2>
        @forelse ($waiting as $t)
            <p><a href="{{ route('admin.ticket', $t->id) }}">{{ $t->reference() }} · {{ $t->subject }}</a><br><span class="ad-muted">{{ $t->store->shop_domain }} · {{ $t->priority }} · {{ $t->last_reply_at?->diffForHumans() }}</span></p>
        @empty <p class="ad-muted">Nothing waiting.</p> @endforelse
    </section>
    <section class="ad-card">
        <h2>Recent installs</h2>
        @forelse ($recentInstalls as $s)
            <p><a href="{{ route('admin.store', $s->id) }}">{{ $s->name ?? $s->shop_domain }}</a><br><span class="ad-muted">{{ $s->shop_domain }} · {{ $s->effectivePlan() ?? 'no plan' }} · {{ $s->installed_at?->diffForHumans() }}{{ $s->uninstalled_at ? ' · uninstalled' : '' }}</span></p>
        @empty <p class="ad-muted">No installs yet.</p> @endforelse
    </section>
</div>
@endsection
