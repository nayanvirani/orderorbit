@extends('layouts.embedded')

@section('title', 'Revenue & attribution')

@php
    use App\Services\Analytics\Attribution;
    $r = $report;
    $money = fn ($v) => $v === null ? '—' : money($v, $r['currency'] ?? $store->currency);
    $templateName = function ($key) use ($experiences) {
        $e = $experiences->first(fn ($x) => $x->publishedVersion?->template_key === $key);
        return $e && \App\Experiences\Registry::has($e->type) ? (\App\Experiences\Registry::template($e->type, $key)['name'] ?? $key) : $key;
    };
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v={{ filemtime(public_path('css/analytics.css')) }}">
@endpush

@section('content')
<s-page heading="Revenue & attribution">
    @include('app.analytics._nav', ['lockedTitle' => 'Revenue attribution is on Growth and Scale'])

    @if ($r)
        <div class="an-controls">
            <span class="oo-muted">Traffic source model</span>
            <nav class="bx-tabs">@foreach (Attribution::MODELS as $key => $label)<a href="{{ request()->fullUrlWithQuery(['model' => $key]) }}" class="{{ $model === $key ? 'on' : '' }}">{{ $label }}</a>@endforeach</nav>
            <span class="oo-muted">Attribution window</span>
            <nav class="bx-tabs">@foreach (Attribution::WINDOWS as $key => $label)<a href="{{ request()->fullUrlWithQuery(['window' => $key]) }}" class="{{ $window === $key ? 'on' : '' }}">{{ $label }}</a>@endforeach</nav>
        </div>

        <s-section heading="Overview">
            <div class="ob-kpis">
                <x-app.kpi label="Revenue" icon="chart" tone="upsells" :value="$money($r['revenue'])" :sub="number_format($r['orders']).' '.\Illuminate\Support\Str::plural('order', $r['orders'])" />
                <x-app.kpi label="AOV" icon="cart" tone="gifts" :value="$money($r['aov'])" sub="Average order value" />
                <x-app.kpi label="Revenue per session" icon="target" tone="countdown" :value="$money($r['per_session'])" :sub="number_format($r['sessions']).' sessions'" />
                <x-app.kpi label="Revenue per visitor" icon="users" tone="analytics" :value="$money($r['per_visitor'])" :sub="number_format($r['visitors']).' visitors'" />
                <x-app.kpi label="Orders influenced" icon="sparkle" tone="bundles" :value="number_format($r['influenced_orders'])" :sub="$money($r['influenced_revenue']).' in orders where shoppers used an offer within '.Attribution::WINDOWS[$window]" />
            </div>
        </s-section>

        <s-section heading="By traffic source · {{ Attribution::MODELS[$model] }}">
            <p class="oo-muted oo-small">{{ $model === 'first' ? 'Each order goes to the source of the shopper\'s first visit within the '.Attribution::WINDOWS[$window].' before it.' : 'Each order goes to the source of the visit it was placed in.' }} Sources come from UTM tags, then the referring site; visits with neither are "direct".</p>
            @include('app.analytics._revenue_table', ['rows' => $r['by_source'], 'label' => 'Source', 'total' => $r['revenue']])
        </s-section>

        @if ($r['by_campaign'])
            <s-section heading="By UTM campaign · {{ Attribution::MODELS[$model] }}">
                @include('app.analytics._revenue_table', ['rows' => $r['by_campaign'], 'label' => 'Campaign', 'total' => $r['revenue']])
            </s-section>
        @endif

        <s-section heading="By experience">
            <p class="oo-muted oo-small"><b>Direct</b>: the order lines an experience added (bundles, gifts, upsells). <b>Assisted</b>: orders from shoppers who saw or used it within {{ Attribution::WINDOWS[$window] }} before buying; each experience gets the whole order, so assisted totals overlap.</p>
            @if (! $r['by_experience'])
                <s-paragraph><span class="oo-muted">No orders with an experience in this period.</span></s-paragraph>
            @else
                <div class="oo-scroll">
                    <table class="oo-table stack">
                        <thead><tr><th>Experience</th><th>Direct orders</th><th>Direct revenue</th><th>Assisted orders</th><th>Assisted revenue</th></tr></thead>
                        <tbody>
                            @foreach ($r['by_experience'] as $handle => $row)
                                <tr>
                                    <td data-label="Experience">@if ($e = $experiences[$handle] ?? null)<s-link href="{{ app_route('app.cro.experiences.show', ['experience' => $e->id]) }}">{{ $e->name }}</s-link>@else<span class="oo-muted">{{ $handle }}</span>@endif</td>
                                    <td data-label="Direct orders">{{ number_format($row['direct_orders']) }}</td>
                                    <td data-label="Direct revenue">{{ $money($row['direct_revenue']) }}</td>
                                    <td data-label="Assisted orders">{{ number_format($row['assisted_orders']) }}</td>
                                    <td data-label="Assisted revenue">{{ $money($row['assisted_revenue']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </s-section>

        @if ($r['by_template'])
            <s-section heading="By template">
                <table class="oo-table stack">
                    <thead><tr><th>Template</th><th>Experiences</th><th>Direct revenue</th><th>Assisted revenue</th></tr></thead>
                    <tbody>
                        @foreach ($r['by_template'] as $key => $row)
                            <tr>
                                <td data-label="Template">{{ $templateName($key) }}</td>
                                <td data-label="Experiences">{{ $row['experiences'] }}</td>
                                <td data-label="Direct revenue">{{ $money($row['direct_revenue']) }}</td>
                                <td data-label="Assisted revenue">{{ $money($row['assisted_revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </s-section>
        @endif

        <s-section heading="By experiment variant">
            <s-paragraph><span class="oo-muted">Revenue per A/B test variant appears here once A/B testing is released.</span></s-paragraph>
        </s-section>

        <p class="oo-muted oo-small">Attribution is an analytical model, not proof that an offer or channel caused a sale. Orders count when the shopper allowed analytics.</p>
    @endif
</s-page>
@endsection
