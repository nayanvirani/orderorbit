{{-- Analytics sections and the date range (kept when switching sections). --}}
@php
    $tabs = ['app.analytics' => 'Overview', 'app.analytics.events' => 'Events', 'app.analytics.funnels' => 'Funnels', 'app.analytics.revenue' => 'Revenue & attribution', 'app.analytics.journeys' => 'Customer journeys'];
    $current = request()->route()->getName();
    $current = $current === 'app.analytics.funnel' ? 'app.analytics.funnels' : ($current === 'app.analytics.journey' ? 'app.analytics.journeys' : $current);
    $days = $days ?? 30;
@endphp
@if ($current !== 'app.analytics')<s-link slot="breadcrumb-actions" href="{{ app_route('app.analytics') }}">Analytics</s-link>@endif
<nav class="oo-tabs" aria-label="Analytics">
    @foreach ($tabs as $route => $label)
        <a href="{{ app_route($route, ['days' => $days]) }}" @if ($current === $route) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@if (empty($hideRange))
    <nav class="bx-tabs" aria-label="Date range">
        @foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $d => $label)
            <a href="{{ request()->fullUrlWithQuery(['days' => $d, 'page' => null]) }}" class="{{ $days === $d ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>
@endif
@php($freshest = \App\Models\AnalyticsEvent::where('store_id', $store->id)->max('occurred_at'))
<p class="oo-muted oo-small an-fresh">Data as of {{ $freshest ? \Illuminate\Support\Carbon::parse($freshest)->diffForHumans() : 'no events yet' }} · only shoppers who allow analytics are counted.</p>
@if (request('error'))
    <s-banner tone="critical">{{ request('error') }}</s-banner>
@endif
@if ($locked ?? false)
    <s-banner tone="info" heading="{{ $lockedTitle ?? 'This report is on Growth and Scale' }}">
        <s-paragraph>{{ $lockedText ?? 'Event Explorer, funnels, revenue attribution and customer journeys come with the Growth and Scale plans. Your store\'s events are already being recorded, so the reports are full from the day you upgrade.' }}</s-paragraph>
        <s-button slot="secondary-actions" href="{{ app_route('app.settings.billing') }}">See plans</s-button>
    </s-banner>
@endif
