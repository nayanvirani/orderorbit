@extends('layouts.embedded')

@section('title', 'Analytics')

@php
    $s = $summary;
    $money = fn ($v) => $v === null ? '—' : money($v, $s['currency']);
    $max = max(1, collect($s['daily'])->max('revenue'));
    $rows = collect($s['experiences'])->map(fn ($r, $handle) => $r + ['handle' => $handle, 'experience' => $experiences[$handle] ?? null])->sortByDesc('revenue');
    $typeLabel = fn ($e) => $e ? (\App\Experiences\Registry::has($e->type) ? \App\Experiences\Registry::type($e->type)['label'] : $e->type) : 'Removed';
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
@endpush

@section('content')
<s-page heading="Analytics">
    <x-app.hero eyebrow="Analytics" title="What your offers <em>earn.</em>"
        lead="Revenue, orders and conversion from your store, and how much of it came from lines your bundles, gifts and upsells added." />

    @if (! $connected)
        <s-banner tone="warning" heading="Analytics isn't connected yet">
            <s-paragraph>{{ $error ? 'Shopify said: '.$error : 'Connect the OrderOrbit Space pixel to start recording visits and orders.' }} If the app asks for permissions, approve them and try again.</s-paragraph>
            <form method="POST" action="{{ app_route('app.analytics.connect') }}" slot="secondary-actions"><s-button type="submit">Connect analytics</s-button></form>
        </s-banner>
    @elseif (! $s['last_event_at'])
        <s-banner tone="info" heading="Connected — waiting for the first visit">
            <s-paragraph>Numbers appear after shoppers visit your store. Orders placed before analytics was connected aren't included. Shopify only sends data for shoppers who allow analytics.</s-paragraph>
        </s-banner>
    @endif

    <nav class="bx-tabs" aria-label="Date range" style="margin-bottom:12px">
        @foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $d => $label)
            <a href="{{ app_route('app.analytics', ['days' => $d]) }}" class="{{ $s['days'] === $d ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <s-section heading="OrderOrbit Space impact">
        <div class="ob-kpis">
            <div class="ob-kpi"><small>Revenue from offers</small><b>{{ $money($s['influenced_revenue']) }}</b><span>Lines added by bundles, gifts and upsells</span></div>
            <div class="ob-kpi"><small>Orders with an offer</small><b>{{ number_format($s['influenced_orders']) }}</b><span>{{ $s['orders'] ? round($s['influenced_orders'] / $s['orders'] * 100).'% of orders' : 'No orders yet' }}</span></div>
            <div class="ob-kpi"><small>AOV with an offer</small><b>{{ $money($s['influenced_aov']) }}</b><span>{{ $s['aov'] && $s['influenced_aov'] ? (($d = $s['influenced_aov'] - $s['aov']) >= 0 ? '+' : '−').$money(abs($d)).' vs all orders' : 'Average order value' }}</span></div>
        </div>
    </s-section>

    <s-section heading="Store">
        <div class="ob-kpis">
            <div class="ob-kpi"><small>Revenue</small><b>{{ $money($s['revenue']) }}</b><span>{{ number_format($s['orders']) }} {{ \Illuminate\Support\Str::plural('order', $s['orders']) }}</span></div>
            <div class="ob-kpi"><small>AOV</small><b>{{ $money($s['aov']) }}</b><span>Average order value</span></div>
            <div class="ob-kpi"><small>Conversion rate</small><b>{{ $s['conversion'] === null ? '—' : number_format($s['conversion'], 2).'%' }}</b><span>{{ number_format($s['sessions']) }} {{ \Illuminate\Support\Str::plural('session', $s['sessions']) }}</span></div>
        </div>
        <div class="an-chart" role="img" aria-label="Daily revenue, with the part from offers highlighted">
            @foreach ($s['daily'] as $day => $v)
                <span title="{{ \Illuminate\Support\Carbon::parse($day)->toFormattedDateString() }}: {{ $money($v['revenue']) }} ({{ $money($v['influenced']) }} from offers)">
                    <i style="height:{{ $v['revenue'] / $max * 100 }}%"><b style="height:{{ $v['revenue'] ? min(100, $v['influenced'] / $v['revenue'] * 100) : 0 }}%"></b></i>
                </span>
            @endforeach
        </div>
        <p class="an-legend"><span><i class="all"></i>Revenue</span><span><i class="oo"></i>From offers</span></p>
    </s-section>

    <s-section heading="By offer">
        @if ($rows->isEmpty())
            <s-paragraph><span class="oo-muted">Views, adds to cart and revenue for each bundle, gift and upsell appear here.</span></s-paragraph>
        @else
            <div class="oo-scroll">
                <table class="oo-table stack">
                    <thead><tr><th>Offer</th><th>Type</th><th>Views</th><th>Added to cart</th><th>Orders</th><th>Revenue</th><th>Conversion</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td data-label="Offer">@if ($r['experience'])<s-link href="{{ app_route('app.cro.experiences.show', ['experience' => $r['experience']->id]) }}">{{ $r['experience']->name }}</s-link>@else<span class="oo-muted">{{ $r['handle'] }}</span>@endif</td>
                                <td data-label="Type">{{ $typeLabel($r['experience']) }}</td>
                                <td data-label="Views">{{ number_format($r['views']) }}</td>
                                <td data-label="Added to cart">{{ number_format($r['adds']) }}</td>
                                <td data-label="Orders">{{ number_format($r['orders']) }}</td>
                                <td data-label="Revenue">{{ $money($r['revenue']) }}</td>
                                <td data-label="Conversion">{{ $r['views'] ? number_format($r['orders'] / $r['views'] * 100, 1).'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </s-section>
</s-page>
@endsection
