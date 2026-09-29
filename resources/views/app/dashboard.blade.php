@extends('layouts.embedded')

@section('title', 'Dashboard')

@section('content')
<s-page heading="Dashboard">
    @foreach ($alerts as $alert)
        <s-banner tone="{{ $alert['tone'] }}">
            <s-paragraph>{{ $alert['text'] }}</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route($alert['route']) }}">{{ $alert['action'] }}</s-button>
        </s-banner>
    @endforeach

    @unless ($store->hasPlanAccess())
        <s-banner tone="warning" heading="Choose a plan to start publishing">
            <s-paragraph>Pick Starter, Growth or Scale on Shopify's plan page. Billing runs through your Shopify invoice.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ $store->pricingUrl() }}" target="_top">Choose a plan</s-button>
        </s-banner>
    @endunless

    @unless ($checklistDone)
        <s-section heading="Set up OrderOrbit">
            <s-stack gap="small-200">
                @foreach ($checklist as $item)
                    <s-stack direction="inline" gap="small-200" alignItems="center">
                        <s-badge tone="{{ $item['done'] ? 'success' : 'neutral' }}">{{ $item['done'] ? 'Done' : 'To do' }}</s-badge>
                        @if (! $item['done'] && isset($item['route']))
                            <s-link href="{{ app_route($item['route']) }}">{{ $item['label'] }}</s-link>
                        @else
                            <s-text>{{ $item['label'] }}</s-text>
                        @endif
                    </s-stack>
                @endforeach
            </s-stack>
        </s-section>
    @endunless

    <s-section heading="Performance">
        <s-grid gridTemplateColumns="repeat(auto-fit, minmax(160px, 1fr))" gap="base">
            @foreach (['Revenue Influenced', 'Conversion Rate', 'AOV', 'CRO Revenue'] as $kpi)
                <s-box padding="base" border="base" borderRadius="base">
                    <s-text color="subdued">{{ $kpi }}</s-text>
                    <s-heading>—</s-heading>
                    <s-text color="subdued">Collecting data</s-text>
                </s-box>
            @endforeach
        </s-grid>
    </s-section>

    <s-section heading="Recent activity">
        @forelse ($recent as $log)
            <s-paragraph><strong>{{ $log->actor?->displayName() ?? 'OrderOrbit' }}</strong> · {{ str_replace(['.', '_'], [' ', ' '], $log->action) }} <span class="oo-muted">· {{ $log->created_at?->diffForHumans() }}</span></s-paragraph>
        @empty
            <s-paragraph>No activity yet.</s-paragraph>
        @endforelse
        @if (request()->attributes->get('storeUser')?->can('view_activity'))
            <s-link href="{{ app_route('app.settings.activity') }}">View all activity</s-link>
        @endif
    </s-section>
</s-page>
@endsection
