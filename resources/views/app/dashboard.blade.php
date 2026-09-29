@extends('layouts.embedded')

@section('title', 'Dashboard')

@section('content')
<s-page heading="Dashboard">
    @if ($store->plan === null)
        <s-banner tone="warning" heading="Choose a plan to start publishing">
            <s-paragraph>Pick Starter, Growth or Scale. Billing runs through Shopify and includes a free trial.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route('app.billing') }}">View plans</s-button>
        </s-banner>
    @endif

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
        <s-grid gridTemplateColumns="repeat(auto-fit, minmax(180px, 1fr))" gap="base">
            @foreach (['Revenue Influenced', 'Conversion Rate', 'AOV', 'CRO Revenue'] as $kpi)
                <s-box padding="base" border="base" borderRadius="base">
                    <s-text color="subdued">{{ $kpi }}</s-text>
                    <s-heading>—</s-heading>
                    <s-text color="subdued">Collecting data</s-text>
                </s-box>
            @endforeach
        </s-grid>
    </s-section>

    <s-section heading="Store">
        <s-paragraph>{{ $store->name ?? $store->shop_domain }} · {{ $store->currency ?? '—' }} · Plan: {{ $store->plan ? config("shopify.billing.plans.{$store->plan}.name") : 'None' }}</s-paragraph>
    </s-section>
</s-page>
@endsection
