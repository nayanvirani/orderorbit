@extends('layouts.embedded')

@section('title', 'Settings · Billing')

@php
    $effective = $store->effectivePlan();
    $planName = fn ($key) => config("shopify.billing.plans.{$key}.name", ucfirst((string) $key));
@endphp

@section('content')
<s-page heading="{{ $effective ? 'Settings' : 'Choose a plan' }}">
    @if ($effective)
        @include('app.settings._tabs')
    @else
        <x-app.hero eyebrow="Plans" title="Choose your <em>orbit.</em>"
            lead="Choose a plan below to start using OrderOrbit Space. Every plan has every feature; start free and upgrade as your store grows. Billing runs through your Shopify invoice." />
    @endif

    @if ($syncError)
        <s-banner tone="critical">Your Shopify connection needs attention. Open Settings → Store to review it.</s-banner>
    @endif

    @if ($subscription && $store->plan)
        <s-section heading="Current plan">
            <s-stack direction="inline" gap="small-200" alignItems="center">
                <s-heading>{{ $planName($subscription->plan) }} · {{ $subscription->price > 0 ? '$'.number_format($subscription->price, 2).'/mo' : 'Free' }}</s-heading>
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
            <s-stack direction="inline" gap="small-200">
                @if ($canManage)
                    <s-button variant="primary" href="{{ $store->pricingUrl() }}" target="_top">Change plan</s-button>
                @endif
                <s-button variant="tertiary" href="{{ $store->adminUrl('settings/billing') }}" target="_top">Open Shopify billing</s-button>
            </s-stack>
        </s-section>
    @elseif ($store->plan && $store->plan_expires_at?->isFuture())
        <s-banner tone="warning" heading="Your plan was cancelled">
            <s-paragraph>You keep {{ $planName($store->plan) }} until {{ $store->plan_expires_at->toFormattedDateString() }}, the end of the period you've paid for. Choose a plan to keep publishing after that. Your data is kept either way.</s-paragraph>
            @if ($canManage)<s-button slot="secondary-actions" href="{{ $store->pricingUrl() }}" target="_top">Choose a plan</s-button>@endif
        </s-banner>
    @elseif ($store->isTestShop())
        <s-banner tone="info" heading="Test store">
            <s-paragraph>This development store has {{ $planName($effective) }} access without a subscription, for testing only.</s-paragraph>
        </s-banner>
    @elseif ($latest && in_array($latest->status, ['PENDING', 'FROZEN', 'DECLINED', 'EXPIRED', 'CANCELLED'], true))
        @php
        $state = [
            'PENDING' => ['warning', 'Waiting for approval', 'Approve the plan on Shopify to activate it.'],
            'FROZEN' => ['critical', 'Frozen', 'Your Shopify account has a billing issue. Resolve it in Shopify to reactivate OrderOrbit Space. Your data is kept.'],
            'DECLINED' => ['warning', 'Not approved', 'The plan wasn\'t approved. Choose a plan below to continue.'],
            'EXPIRED' => ['warning', 'Approval expired', 'The approval request expired. Choose a plan below to continue.'],
            'CANCELLED' => ['warning', 'Cancelled', 'Your plan was cancelled. Choose a plan below to continue. Your data is kept.'],
        ][$latest->status];
        @endphp
        <s-banner tone="{{ $state[0] }}" heading="Plan status: {{ $state[1] }}"><s-paragraph>{{ $state[2] }}</s-paragraph></s-banner>
    @endif

    @if ($effective)
    @php
        $money = fn ($v) => '$'.number_format((float) $v);
    @endphp
    <s-section heading="Store sales · this cycle">
        @if ($sales['state'] === 'paused')
            <s-banner tone="critical" heading="All features are stopped">
                <s-paragraph>Your store passed this plan's sales limit and the {{ config('shopify.billing.grace_days') }}-day period to upgrade has ended. Choose a higher plan and everything goes live again straight away. Nothing was deleted.@if ($sales['next']) {{ $sales['next']['name'] }} (${{ number_format($sales['next']['price'], 2) }}/mo) fits your store.@endif</s-paragraph>
            </s-banner>
        @elseif ($sales['state'] === 'over')
            <s-banner tone="warning" heading="Upgrade required by {{ $sales['deadline']->toFormattedDateString() }}">
                <s-paragraph>Your store has passed this plan's sales limit. Everything keeps working until {{ $sales['deadline']->toFormattedDateString() }}; after that every feature stops until you upgrade.@if ($sales['next']) {{ $sales['next']['name'] }} (${{ number_format($sales['next']['price'], 2) }}/mo) fits your store.@endif</s-paragraph>
            </s-banner>
        @elseif ($sales['state'] === 'near')
            <s-banner tone="info" heading="You're close to this plan's sales limit">
                <s-paragraph>When your store passes the limit you'll have {{ config('shopify.billing.grace_days') }} days to upgrade before every feature stops.</s-paragraph>
            </s-banner>
        @endif
        <div class="ob-sales">
            <div class="ob-sales-top">
                @if ($sales['sales'] === null)
                    <b>Not counted yet</b>
                    <span>Your sales are counted from your Shopify orders shortly after you open the app.</span>
                @else
                    <b>{{ $money($sales['sales']) }}</b>
                    <span>{{ $sales['limit'] === null ? 'Unlimited on your plan' : 'of '.$money($sales['limit']).' on your plan ('.$sales['percent'].'%)' }}</span>
                @endif
            </div>
            @if ($sales['limit'] !== null && $sales['sales'] !== null)
                <div class="ob-sales-bar"><i class="{{ in_array($sales['state'], ['over', 'paused'], true) ? 'over' : ($sales['state'] === 'near' ? 'near' : '') }}" style="width:{{ min(100, (int) $sales['percent']) }}%"></i></div>
            @endif
            @if ($sales['sales'] !== null)
                <span class="oo-muted">{{ number_format($sales['orders']) }} {{ \Illuminate\Support\Str::plural('order', $sales['orders']) }} counted this cycle.@if ($sales['test_orders']) {{ $sales['test_orders']['count'] }} test {{ \Illuminate\Support\Str::plural('order', $sales['test_orders']['count']) }} ({{ $money($sales['test_orders']['usd']) }}) {{ $sales['test_orders']['count'] === 1 ? 'is' : 'are' }} not counted: orders paid with a test payment, like every order on a development store, never count toward your limit.@endif</span>
            @endif
            <span class="oo-muted">Cycle {{ $sales['cycle_start']->toFormattedDateString() }} – {{ $sales['cycle_end']->toFormattedDateString() }}. The count starts again on {{ $sales['cycle_end']->toFormattedDateString() }}. Every plan includes every feature; plans differ only by your store's total sales per 30-day cycle (all orders, in USD; test and cancelled orders don't count).</span>
        </div>
        @if ($cycles->isNotEmpty())
            <table class="oo-table" style="margin-top:14px">
                <thead><tr><th>Past cycle</th><th>Orders</th><th>Store sales</th><th>Plan</th><th></th></tr></thead>
                <tbody>
                    @foreach ($cycles as $cycle)
                        <tr>
                            <td>{{ $cycle->starts_at->toFormattedDateString() }} – {{ $cycle->ends_at->toFormattedDateString() }}</td>
                            <td>{{ number_format($cycle->orders_count) }}</td>
                            <td>{{ $money($cycle->sales_usd) }}</td>
                            <td>{{ $planName($cycle->plan) }}@if ($cycle->sales_limit !== null) · up to {{ $money($cycle->sales_limit) }}@endif</td>
                            <td>@if ($cycle->over_limit)<s-badge tone="warning">Over limit</s-badge>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </s-section>

    @endif

    <s-section heading="{{ $effective ? 'Plans' : 'Pick a plan' }}">
        <div class="ob-plans">
            @foreach ($plans as $key => $plan)
                @php($isCurrent = $effective === $key)
                <div class="ob-plan {{ $key === 'growth' ? 'featured' : '' }} {{ $isCurrent ? 'current' : '' }}">
                    @if ($isCurrent)
                        <span class="ob-badge">Current plan</span>
                    @elseif ($key === 'growth')
                        <span class="ob-badge">Most popular</span>
                    @endif
                    <h3>{{ $plan['name'] }}</h3>
                    <div class="ob-price">{{ $plan['price'] > 0 ? '$'.number_format($plan['price'], 2) : 'Free' }} @if ($plan['price'] > 0)<small>/MO</small>@endif</div>
                    <ul>
                        @foreach ($plan['features'] as $feature)<li>{{ $feature }}</li>@endforeach
                    </ul>
                    @if ($canManage)
                        {{-- Shopify's hosted plan page (Managed Pricing); it returns to the app with ?charge_id. --}}
                        <s-button variant="{{ $isCurrent ? 'secondary' : 'primary' }}" href="{{ $store->pricingUrl() }}" target="_top">{{ $isCurrent ? 'Manage plan' : 'Choose plan' }}</s-button>
                    @endif
                </div>
            @endforeach
        </div>
        @unless ($canManage)
            <s-paragraph><span class="oo-muted">Only store owners can change the plan.</span></s-paragraph>
        @endunless
    </s-section>

    <s-paragraph><span class="oo-muted">Billed through Shopify. Plan changes happen on Shopify's plan page. Nothing is ever deleted when you change plans. If your store's sales pass your plan's limit you have {{ config('shopify.billing.grace_days') }} days to upgrade before features stop.</span></s-paragraph>
</s-page>
@endsection
