@extends('layouts.embedded')

@section('title', 'Settings · Store')

@php
    $canManage = request()->attributes->get('storeUser')?->can('manage_settings');
    $cap = fn ($key) => $store->capability($key);
    $yesNo = fn ($value, $yes, $no, $unknown = 'Not checked yet') => $value === null ? ['neutral', $unknown] : ($value ? ['success', $yes] : ['warning', $no]);
@endphp

@section('content')
<s-page inlineSize="large" heading="Settings">
    @include('app.settings._tabs')

    @if ($missingScopes)
        <s-banner tone="critical" heading="Your Shopify connection needs attention">
            <s-paragraph>OrderOrbit Space is missing permissions it needs: {{ implode(', ', $missingScopes) }}. Reconnect to approve them.</s-paragraph>
        </s-banner>
    @endif

    <s-section heading="Shopify connection">
        <dl class="oo-kv">
            <dt>Status</dt>
            <dd>
                @if ($store->isInstalled() && ! $missingScopes)
                    <s-badge tone="success">Connected</s-badge>
                @else
                    <s-badge tone="critical">Needs attention</s-badge>
                @endif
            </dd>
            <dt>Store</dt><dd>{{ $store->name ?? '—' }} <span class="oo-muted">· {{ $store->shop_domain }}</span></dd>
            <dt>Shopify plan</dt><dd>{{ $store->shopify_plan ?? '—' }}</dd>
            <dt>Currency</dt><dd>{{ $store->currency ?? '—' }}</dd>
            <dt>Selling currencies</dt><dd>{{ implode(', ', $cap('presentment_currencies') ?? []) ?: '—' }}</dd>
            <dt>Timezone</dt><dd>{{ $store->timezone ?? '—' }}</dd>
            <dt>Installed</dt><dd>{{ $store->installed_at?->toFormattedDateString() ?? '—' }}</dd>
            <dt>Granted permissions</dt>
            <dd class="oo-inline">@forelse ($grantedScopes as $scope)<span class="oo-code">{{ $scope }}</span>@empty — @endforelse</dd>
        </dl>
        @if ($canManage)
            <s-stack direction="inline" gap="small-200" paddingBlockStart="base">
                <form method="POST" action="{{ app_route('app.settings.store.reconnect') }}"><s-button type="submit">Reconnect</s-button></form>
            </s-stack>
        @endif
    </s-section>

    <s-section heading="Theme">
        <dl class="oo-kv">
            <dt>Live theme</dt><dd>{{ $store->theme_name ?? '—' }}</dd>
            <dt>App blocks</dt>
            <dd>@php([$tone, $text] = $yesNo($cap('online_store_2'), 'Supported (Online Store 2.0)', 'Not supported — switch to an Online Store 2.0 theme'))<s-badge tone="{{ $tone }}">{{ $text }}</s-badge></dd>
            <dt>OrderOrbit Space app embed</dt><dd><s-badge>Available with the first OrderOrbit Space block</s-badge></dd>
        </dl>
        <s-stack direction="inline" gap="small-200" paddingBlockStart="base">
            <s-button href="{{ $store->adminUrl('themes/current/editor') }}" target="_top">Open Theme Editor</s-button>
        </s-stack>
    </s-section>

    <s-section heading="What your store supports">
        <s-paragraph>OrderOrbit Space only shows the Shopify surfaces your store can use.</s-paragraph>
        <div class="oo-scroll">
            <table class="oo-table">
                <thead><tr><th>Surface</th><th>Status</th><th>Notes</th></tr></thead>
                <tbody>
                    @php([$t, $x] = $yesNo($cap('online_store_2'), 'Supported', 'Theme update needed'))
                    <tr><td>Storefront blocks</td><td><s-badge tone="{{ $t }}">{{ $x }}</s-badge></td><td class="oo-muted">Bundles, upsells, shipping bar and more, placed in the Theme Editor.</td></tr>
                    @php([$t, $x] = $yesNo($cap('checkout_blocks'), 'Supported', 'Requires Shopify Plus'))
                    <tr><td>Blocks inside checkout</td><td><s-badge tone="{{ $t }}">{{ $x }}</s-badge></td><td class="oo-muted">{{ $cap('development_store') ? 'Development stores can preview checkout blocks.' : 'Trust, reviews, shipping progress and offers in the checkout steps.' }}</td></tr>
                    @php([$t, $x] = $yesNo($cap('thank_you_blocks') ?? true, 'Supported', 'Not available'))
                    <tr><td>Thank You and Order Status</td><td><s-badge tone="{{ $t }}">{{ $x }}</s-badge></td><td class="oo-muted">Cross-sells, reorder, reviews and support after purchase.</td></tr>
                    @php([$t, $x] = $yesNo($cap('new_customer_accounts'), 'Supported', 'Requires new customer accounts'))
                    <tr><td>Customer accounts</td><td><s-badge tone="{{ $t }}">{{ $x }}</s-badge></td><td class="oo-muted">Reorder, rewards and reviews in customer accounts.</td></tr>
                    <tr><td>Analytics</td><td><s-badge tone="{{ $store->web_pixel_id ? 'success' : 'warning' }}">{{ $store->web_pixel_id ? 'Connected' : 'Not connected' }}</s-badge></td><td class="oo-muted">The consent-aware web pixel. <a href="{{ app_route('app.settings.integrations') }}">Details</a></td></tr>
                </tbody>
            </table>
        </div>
        <s-stack direction="inline" gap="small-200" alignItems="center" paddingBlockStart="base">
            @if ($canManage)
                <form method="POST" action="{{ app_route('app.settings.store.recheck') }}"><s-button type="submit">Re-check capabilities</s-button></form>
            @endif
            <span class="oo-muted oo-small">Last checked {{ $store->capabilities_checked_at?->diffForHumans() ?? 'never' }}</span>
        </s-stack>
    </s-section>
</s-page>
@endsection
