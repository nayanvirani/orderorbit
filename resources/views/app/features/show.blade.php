@extends('layouts.embedded')

@section('title', $feature['label'])

@php
    $first = array_key_first($types);
    $discounts = collect($types)->contains(fn ($t) => $t['discount'] ?? false);
    $surface = $types[$first]['surface'];
    $editor = $store->themeEditorUrl($surface);
    $canManage = request()->attributes->get('storeUser')?->can('manage_experiences');
    // Only offer what Shopify allows: blocks inside checkout need Shopify Plus (or a development store).
    $checkoutLocked = $surface === 'checkout' && ! $store->capability('checkout_blocks');
    $needsPlan = in_array($surface, \App\Experiences\Schema::CHECKOUT_SURFACES, true) && ! $store->planIncludes(\App\Models\Store::surfacePlanFeature($surface));
    $legacyAccounts = $surface === 'account' && $store->capability('new_customer_accounts') === false;
@endphp

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
@endpush

@section('content')
<s-page inlineSize="large" heading="{{ $feature['label'] }}">
    <x-app.hero :eyebrow="$feature['label']" :title="$feature['tagline']" :lead="$feature['lead']" :icon="$feature['icon'] ?? null" :tone="$feature['tone'] ?? null">
        @if ($canManage && ! $checkoutLocked)
            @foreach ($types as $typeKey => $type)
                <s-button variant="{{ $loop->first ? 'primary' : 'secondary' }}" href="{{ app_route('app.cro.experiences.create', ['type' => $typeKey]) }}">Create {{ lower_label($type['singular']) }}</s-button>
            @endforeach
        @endif
        <s-button href="{{ $editor }}" target="_top">{{ $store->editorLabel($surface) }}</s-button>
        @php($doc = ['bundles' => 'bundles', 'progressive-gifts' => 'progressive-gifts', 'checkout' => 'checkout-blocks', 'thank-you' => 'checkout-blocks', 'post-purchase' => 'checkout-blocks', 'customer-accounts' => 'customer-accounts'][request()->route('feature')] ?? 'storefront-widgets')
        <s-button href="{{ route('site.docs', $doc) }}" target="_blank" variant="tertiary">View documentation</s-button>
    </x-app.hero>

    @if ($checkoutLocked)
        <s-banner tone="info" heading="Blocks inside checkout need Shopify Plus">
            <s-paragraph>Your store isn't on Shopify Plus, so Shopify doesn't allow apps to add blocks inside checkout. Thank You and Order Status blocks work on every plan.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route('app.features.show', ['feature' => 'thank-you']) }}">Thank You & Order Status</s-button>
        </s-banner>
    @elseif ($legacyAccounts)
        <s-banner tone="warning" heading="Your store uses classic customer accounts">
            <s-paragraph>These blocks need Shopify's new customer accounts. Turn them on in Shopify under Settings → Customer accounts, then re-check your store in OrderOrbit Space Settings. You can set up blocks now.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ $store->adminUrl('settings/customer_accounts') }}" target="_top">Customer account settings</s-button>
        </s-banner>
    @elseif ($needsPlan)
        <s-banner tone="info" heading="On the Growth plan and above">
            <s-paragraph>You can set these blocks up now; publishing them needs the Growth or Scale plan.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route('app.settings.billing') }}">See plans</s-button>
        </s-banner>
    @endif

    <s-section>
        <div class="ob-kpis">
            <div class="ob-kpi"><small>Live</small><b>{{ $counts['published'] ?? 0 }}</b><span>Showing to shoppers</span></div>
            <div class="ob-kpi"><small>Drafts</small><b>{{ $counts['draft'] ?? 0 }}</b><span>Not live yet</span></div>
            <div class="ob-kpi"><small>Paused</small><b>{{ $counts['paused'] ?? 0 }}</b><span>Hidden from shoppers</span></div>
            <div class="ob-kpi"><small>Checkout saving</small><b style="font-size:26px">{{ $discounts ? 'Automatic' : 'Not needed' }}</b><span>{{ $discounts ? 'Applied by OrderOrbit Space in cart and checkout' : 'This feature doesn\'t change prices' }}</span></div>
        </div>
    </s-section>

    <s-section heading="How it works">
        <ol class="ob-how">
            @foreach ($feature['steps'] as $step)<li>{{ $step }}</li>@endforeach
        </ol>
    </s-section>

    <s-section heading="Your {{ lower_label($feature['label']) }}">
        @if ($experiences->isEmpty())
            <x-app.empty :title="'No '.lower_label($feature['label']).' yet'" :text="$types[$first]['empty']">
                @if ($canManage)
                    <s-button variant="primary" href="{{ app_route('app.cro.experiences.create', ['type' => $first]) }}">Create {{ lower_label($types[$first]['singular']) }}</s-button>
                @endif
            </x-app.empty>
        @else
            <div class="oo-scroll">
                <table class="oo-table stack">
                    <thead><tr><th>Name</th>@if (count($types) > 1)<th>Type</th>@endif<th>Status</th><th>Template</th>@if ($discounts)<th>Checkout saving</th>@endif<th>Updated</th></tr></thead>
                    <tbody>
                        @foreach ($experiences as $experience)
                            <tr>
                                <td data-label="Name"><s-link href="{{ app_route('app.cro.experiences.show', ['experience' => $experience->id]) }}">{{ $experience->name }}</s-link></td>
                                @if (count($types) > 1)<td data-label="Type">{{ $types[$experience->type]['label'] }}</td>@endif
                                <td data-label="Status">@include('app.cro._status')</td>
                                <td data-label="Template">{{ $types[$experience->type]['templates'][$experience->template_key]['name'] ?? $experience->template_key }}</td>
                                @if ($discounts)
                                    <td data-label="Checkout saving">
                                        @if ($experience->shopify_discount_id)<s-badge tone="success">Active</s-badge>@else<span class="oo-muted">—</span>@endif
                                    </td>
                                @endif
                                <td data-label="Updated" class="oo-muted">{{ $experience->updated_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </s-section>

    <s-section heading="Start from a template">
        <div class="b-templates" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
            @foreach ($templates as $template)
                <a class="b-template" style="text-decoration:none;color:inherit" href="{{ $canManage ? app_route('app.cro.experiences.create', ['type' => $template['type'], 'template' => $template['key']]) : '#' }}">
                    <span class="b-template-preview oo-preview" style="zoom:.8;max-height:260px" data-render="{{ json_encode($template['preview']) }}"></span>
                    <span class="b-template-name">{{ $template['name'] }}@if (count($types) > 1) <span class="oo-muted">· {{ $types[$template['type']]['label'] }}</span>@endif</span>
                </a>
            @endforeach
        </div>
    </s-section>
</s-page>
@endsection

@push('scripts')
    <script src="{{ route('storefront.asset', 'orderorbit.js') }}"></script>
    <script src="{{ asset('js/checkout-preview.js') }}?v={{ filemtime(public_path('js/checkout-preview.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('css/checkout-preview.css') }}?v={{ filemtime(public_path('css/checkout-preview.css')) }}">
    <script>
        document.querySelectorAll('[data-render]').forEach((el) => {
            window.OrderOrbit.render(el, JSON.parse(el.dataset.render), { preview: true, currency: @json($store->currency ?? 'USD'), cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' });
        });
    </script>
@endpush
