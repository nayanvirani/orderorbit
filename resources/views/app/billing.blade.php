@extends('layouts.embedded')

@section('title', 'Billing')

@section('content')
<s-page heading="Plans & billing">
    @if (request('saved'))
        <s-banner tone="success">Changes saved successfully.</s-banner>
    @endif

    @if ($syncError)
        <s-banner tone="critical">{{ $syncError }}</s-banner>
    @endif

    @if ($subscription)
        <s-section heading="Current plan">
            <s-stack direction="inline" gap="small-200" alignItems="center">
                <s-heading>{{ config("shopify.billing.plans.{$subscription->plan}.name", $subscription->plan) }}</s-heading>
                <s-badge tone="success">Active</s-badge>
                @if ($subscription->test)
                    <s-badge>Test charge</s-badge>
                @endif
            </s-stack>
            @if ($subscription->trial_ends_at?->isFuture())
                <s-paragraph>Trial ends {{ $subscription->trial_ends_at->toFormattedDateString() }}.</s-paragraph>
            @endif
            @if ($subscription->current_period_ends_at)
                <s-paragraph>Renews {{ $subscription->current_period_ends_at->toFormattedDateString() }}.</s-paragraph>
            @endif
        </s-section>
    @endif

    <s-grid gridTemplateColumns="repeat(auto-fit, minmax(240px, 1fr))" gap="base">
        @foreach ($plans as $key => $plan)
            <s-section heading="{{ $plan['name'] }}">
                <s-heading>${{ number_format($plan['price'], 2) }}/mo</s-heading>
                <s-unordered-list>
                    @foreach ($plan['features'] as $feature)
                        <s-list-item>{{ $feature }}</s-list-item>
                    @endforeach
                </s-unordered-list>
                @if ($store->plan === $key)
                    <s-button disabled>Current plan</s-button>
                @else
                    <form method="POST" action="{{ app_route('app.billing.subscribe') }}">
                        <input type="hidden" name="plan" value="{{ $key }}">
                        <s-button type="submit" variant="{{ $key === 'growth' ? 'primary' : 'secondary' }}">
                            {{ $store->plan ? 'Switch to '.$plan['name'] : 'Choose '.$plan['name'] }}
                        </s-button>
                    </form>
                @endif
            </s-section>
        @endforeach
    </s-grid>

    <s-paragraph>Billed through Shopify. Existing data is never deleted on downgrade; over-limit items are paused, not removed.</s-paragraph>
</s-page>
@endsection
