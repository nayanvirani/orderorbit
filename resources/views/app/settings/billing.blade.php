@extends('layouts.embedded')

@section('title', 'Settings · Billing')

@section('content')
<s-page heading="Settings">
    @include('app.settings._tabs')

    @if ($syncError)
        <s-banner tone="critical">Your Shopify connection needs attention. Open Settings → Store to review it.</s-banner>
    @endif

    @if ($subscription)
        <s-section heading="Current plan">
            <s-stack direction="inline" gap="small-200" alignItems="center">
                <s-heading>{{ config("shopify.billing.plans.{$subscription->plan}.name", ucfirst($subscription->plan)) }} · ${{ number_format($subscription->price, 2) }}/mo</s-heading>
                @if ($subscription->trial_ends_at?->isFuture())
                    <s-badge tone="info">Trial</s-badge>
                @else
                    <s-badge tone="success">Active</s-badge>
                @endif
                @if ($subscription->test)<s-badge>Test charge</s-badge>@endif
            </s-stack>
            <s-paragraph>
                @if ($subscription->trial_ends_at?->isFuture())Trial ends {{ $subscription->trial_ends_at->toFormattedDateString() }}. @endif
                @if ($subscription->current_period_ends_at)Renews {{ $subscription->current_period_ends_at->toFormattedDateString() }}.@endif
            </s-paragraph>
            <s-button href="{{ $store->adminUrl('settings/billing') }}" target="_top" variant="tertiary">Open Shopify billing</s-button>
        </s-section>
    @elseif ($latest && in_array($latest->status, ['PENDING', 'FROZEN', 'DECLINED', 'EXPIRED', 'CANCELLED'], true))
        @php($state = ['PENDING' => ['warning', 'Waiting for approval', 'Approve the charge in Shopify to activate your plan.'], 'FROZEN' => ['critical', 'Frozen', 'Your Shopify account has a billing issue. Resolve it in Shopify to reactivate OrderOrbit.'], 'DECLINED' => ['warning', 'Declined', 'The plan was not approved. Choose a plan to continue.'], 'EXPIRED' => ['warning', 'Expired', 'The approval request expired. Choose a plan to continue.'], 'CANCELLED' => ['warning', 'Cancelled', 'Your plan was cancelled. Choose a plan to continue. Your data is kept.']][$latest->status])
        <s-banner tone="{{ $state[0] }}" heading="Plan status: {{ $state[1] }}"><s-paragraph>{{ $state[2] }}</s-paragraph></s-banner>
    @else
        <s-banner tone="info" heading="Choose a plan to start publishing">
            <s-paragraph>Billing runs through Shopify{{ config('shopify.billing.trial_days') ? ' and includes a '.config('shopify.billing.trial_days').'-day free trial' : '' }}.</s-paragraph>
        </s-banner>
    @endif

    <s-section heading="Usage">
        <s-grid gridTemplateColumns="repeat(auto-fit, minmax(200px, 1fr))" gap="base">
            @foreach ($usage as $meter)
                @php($full = $meter['limit'] !== null && $meter['used'] >= $meter['limit'])
                <s-box padding="base" border="base" borderRadius="base">
                    <s-text color="subdued">{{ $meter['label'] }}</s-text>
                    <div><strong>{{ number_format($meter['used']) }}</strong> <span class="oo-muted">/ {{ $meter['limit'] === null ? 'Unlimited' : number_format($meter['limit']) }}</span></div>
                    <div class="oo-meter"><i class="{{ $full && $store->plan ? 'full' : '' }}" style="width:{{ $meter['limit'] ? min(100, round($meter['used'] / $meter['limit'] * 100)) : 0 }}%"></i></div>
                    @if ($full && $store->plan)<s-text tone="critical">You've reached your current OrderOrbit plan limit.</s-text>@endif
                </s-box>
            @endforeach
        </s-grid>
    </s-section>

    @unless ($canManage)
        <s-banner tone="info">Only store owners can change the plan.</s-banner>
    @endunless

    <s-grid gridTemplateColumns="repeat(auto-fit, minmax(240px, 1fr))" gap="base">
        @foreach ($plans as $key => $plan)
            <s-section heading="{{ $plan['name'] }}">
                <s-heading>${{ number_format($plan['price'], 2) }}/mo</s-heading>
                <s-unordered-list>
                    @foreach ($plan['features'] as $feature)<s-list-item>{{ $feature }}</s-list-item>@endforeach
                </s-unordered-list>
                @if ($store->plan === $key)
                    <s-button disabled>Current plan</s-button>
                @elseif ($canManage)
                    <form method="POST" action="{{ app_route('app.settings.billing.subscribe') }}">
                        <input type="hidden" name="plan" value="{{ $key }}">
                        <s-button type="submit" variant="{{ $key === 'growth' ? 'primary' : 'secondary' }}">{{ $store->plan ? ($plan['price'] > config("shopify.billing.plans.{$store->plan}.price", 0) ? 'Upgrade to ' : 'Switch to ').$plan['name'] : 'Choose '.$plan['name'] }}</s-button>
                    </form>
                @endif
            </s-section>
        @endforeach
    </s-grid>

    <s-paragraph><span class="oo-muted">Billed through Shopify. Existing data is never deleted or changed on downgrade; items over the new limit are paused, not removed.</span></s-paragraph>
</s-page>
@endsection
