@extends('admin.layout')
@section('title', $store->shop_domain)
@section('content')
<p><a href="{{ route('admin.stores') }}">← Stores</a></p>
<h1>{{ $store->name ?? $store->shop_domain }} <span class="ad-muted">{{ $store->shop_domain }}</span></h1>
<div class="ad-grid">
    <section class="ad-card">
        <h2>Installation and subscription</h2>
        <dl class="ad-kv">
            <dt>Status</dt><dd>{!! $store->isInstalled() ? '<span class="ad-ok">Installed</span>' : '<span class="ad-bad">Uninstalled '.e($store->uninstalled_at?->toFormattedDateString()).'</span>' !!}</dd>
            <dt>Installed</dt><dd>{{ $store->installed_at?->toDayDateTimeString() ?? '—' }}</dd>
            <dt>OrderOrbit plan</dt><dd>{{ $store->effectivePlan() ?? 'None' }}{{ $store->offersSuspended() ? ' · offers suspended (over sales limit)' : '' }}</dd>
            <dt>Subscription</dt><dd>{{ $subscription ? ($subscription->status ?? '—').' · '.($subscription->name ?? $subscription->plan ?? '') : '—' }}</dd>
            <dt>Shopify plan</dt><dd>{{ $store->shopify_plan ?? '—' }}</dd>
            <dt>Sales this cycle</dt><dd>${{ number_format($sales['sales'] ?? 0, 2) }} of {{ $sales['limit'] ? '$'.number_format($sales['limit']) : 'unlimited' }} · {{ $sales['orders'] ?? 0 }} orders</dd>
            <dt>Currency / timezone</dt><dd>{{ $store->currency }} · {{ $store->timezone }}</dd>
            <dt>Theme</dt><dd>{{ $store->theme_name ?? '—' }}</dd>
        </dl>
    </section>
    <section class="ad-card">
        <h2>Extension health</h2>
        <dl class="ad-kv">
            <dt>Web pixel</dt><dd>{!! $store->web_pixel_id ? '<span class="ad-ok">Connected</span>' : '<span class="ad-bad">Not connected</span>' !!}</dd>
            <dt>Online Store 2.0</dt><dd>{{ var_export($store->capability('online_store_2'), true) }}</dd>
            <dt>Checkout blocks</dt><dd>{{ var_export($store->capability('checkout_blocks'), true) }}</dd>
            <dt>New customer accounts</dt><dd>{{ var_export($store->capability('new_customer_accounts'), true) }}</dd>
            <dt>Missing scopes</dt><dd>{{ implode(', ', $store->missingScopes()) ?: 'None' }}</dd>
            <dt>Capabilities checked</dt><dd>{{ $store->capabilities_checked_at?->diffForHumans() ?? 'never' }}</dd>
        </dl>
    </section>
</div>
<div class="ad-grid">
    <section class="ad-card">
        <h2>Experiences</h2>
        <table class="ad-table"><thead><tr><th>Type</th><th>Status</th><th>Count</th></tr></thead><tbody>
            @forelse ($experiences as $e)<tr><td>{{ $e->type }}</td><td>{{ $e->status }}</td><td>{{ $e->n }}</td></tr>@empty<tr><td colspan="3" class="ad-muted">None</td></tr>@endforelse
        </tbody></table>
    </section>
    <section class="ad-card">
        <h2>Event volume, 14 days</h2>
        @php($max = max(1, $eventsByDay->max() ?? 1))
        <div class="ad-bars">@foreach ($eventsByDay as $day => $n)<span title="{{ $day }}: {{ $n }}"><i style="height:{{ $n / $max * 100 }}%"></i></span>@endforeach</div>
        <p class="ad-muted">{{ number_format($eventsByDay->sum()) }} events</p>
    </section>
</div>
<section class="ad-card">
    <h2>Usage records</h2>
    <table class="ad-table"><thead><tr><th>Meter</th><th>Value</th><th>Period</th></tr></thead><tbody>
        @forelse ($usage as $u)<tr><td>{{ $u->meter ?? $u->metric ?? '—' }}</td><td>{{ $u->quantity ?? $u->count ?? $u->value ?? '—' }}</td><td>{{ $u->period_start ?? $u->created_at }}</td></tr>@empty<tr><td colspan="3" class="ad-muted">None</td></tr>@endforelse
    </tbody></table>
</section>
<div class="ad-grid">
    <section class="ad-card">
        <h2>Failed workflow runs</h2>
        @forelse ($failedRuns as $r)<p>#{{ $r->id }} {{ $r->workflow?->name }} <span class="ad-muted">· {{ $r->updated_at->diffForHumans() }}</span><br><span class="ad-bad">{{ \Illuminate\Support\Str::limit($r->error, 160) }}</span></p>@empty<p class="ad-muted">None.</p>@endforelse
    </section>
    <section class="ad-card">
        <h2>Support tickets</h2>
        @forelse ($tickets as $t)<p><a href="{{ route('admin.ticket', $t->id) }}">{{ $t->reference() }} · {{ $t->subject }}</a> <span class="ad-muted">· {{ $t->status }}</span></p>@empty<p class="ad-muted">None.</p>@endforelse
    </section>
</div>
<div class="ad-grid">
    <section class="ad-card">
        <h2>Recent webhooks</h2>
        <table class="ad-table"><thead><tr><th>Topic</th><th>Received</th><th>Processed</th></tr></thead><tbody>
            @forelse ($webhooks as $w)<tr><td>{{ $w->topic }}</td><td>{{ $w->created_at->diffForHumans() }}</td><td>{!! $w->processed_at ? '<span class="ad-ok">Yes</span>' : '<span class="ad-bad">No</span>' !!}</td></tr>@empty<tr><td colspan="3" class="ad-muted">None</td></tr>@endforelse
        </tbody></table>
    </section>
    <section class="ad-card">
        <h2>Audit log</h2>
        @forelse ($audit as $a)<p><code>{{ $a->action }}</code> <span class="ad-muted">· {{ $a->created_at->diffForHumans() }}</span></p>@empty<p class="ad-muted">None.</p>@endforelse
    </section>
</div>
@endsection
