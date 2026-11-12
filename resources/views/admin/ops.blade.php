@extends('admin.layout')
@section('title', 'Ops alerts')
@section('content')
<div class="ad-head"><div><h1>Ops alerts</h1><p>Checked every 5 minutes: the website and app responding, Shopify webhooks, workflow runs, email sending and Shopify token refreshes. Each alert is emailed at most once an hour while it lasts, and closes by itself when the problem is gone. A daily summary goes out at 09:00 India time.</p></div></div>
<section class="ad-card">
    <h2>Outgoing IP addresses</h2>
    <p class="ad-muted">The addresses Growvia's servers use when they call Shopify. Add every one of these to Dev Dashboard › Growvia › Settings › IP allowlist; if Shopify's logs show "IP blocked", the address there is missing from the list.</p>
    <div class="ad-scroll"><table class="ad-table">
        <thead><tr><th>Service</th><th>IPv4</th><th>IPv6</th><th>Checked</th></tr></thead>
        <tbody>
            @foreach ($ips as $service => $ip)
                <tr><td>{{ $service }}</td>
                    <td><code>{{ $ip['v4'] ?? '—' }}</code></td>
                    <td><code>{{ $ip['v6'] ?? 'not used' }}</code></td>
                    <td>{{ ! empty($ip['at']) ? \Illuminate\Support\Carbon::parse($ip['at'])->diffForHumans() : 'not yet (the worker records it every 10 minutes)' }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
</section>

<section class="ad-card">
    <p>Alerts and the daily summary go to <b>{{ $recipient ?? 'nobody yet' }}</b> · <a href="{{ route('admin.email') }}">change in Email providers</a><br>
    <span class="ad-muted">Last check: {{ $lastRun ? $lastRun->diffForHumans() : 'not yet (the worker runs it)' }}@if ($lastRun && $lastRun->lt(now()->subMinutes(15))) · <b style="color:var(--bad)">the checks have stopped: is the worker running?</b>@endif</span></p>
</section>
<section class="ad-card flush"><div class="ad-scroll">
<table class="ad-table">
    <thead><tr><th>Open now</th><th>First seen</th><th>Last seen</th><th>Checks</th><th></th></tr></thead>
    <tbody>
        @forelse ($open as $a)
            <tr><td><b>{{ $a->title }}</b><br><span class="ad-muted">{{ $a->details }}</span></td><td>{{ $a->first_seen_at->diffForHumans() }}</td><td>{{ $a->last_seen_at->diffForHumans() }}</td><td>{{ $a->seen }}</td>
                <td><form method="POST" action="{{ route('admin.ops.resolve', $a->id) }}">@csrf<button class="ad-btn small" type="submit">Close</button></form></td></tr>
        @empty <tr><td colspan="5" class="ad-muted">✓ Nothing is wrong right now.</td></tr> @endforelse
    </tbody>
</table>
</div></section>
<section class="ad-card flush"><div class="ad-scroll">
<table class="ad-table">
    <thead><tr><th>Closed in the last 30 days</th><th>Lasted</th><th>Closed</th></tr></thead>
    <tbody>
        @forelse ($recent as $a)
            <tr><td>{{ $a->title }}</td><td>{{ $a->first_seen_at->diffForHumans($a->resolved_at, true) }}</td><td>{{ $a->resolved_at->diffForHumans() }}</td></tr>
        @empty <tr><td colspan="3" class="ad-muted">None.</td></tr> @endforelse
    </tbody>
</table>
</div></section>
@endsection
