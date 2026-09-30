@extends('layouts.embedded')

@section('title', $feature['label'])

@php
    $first = array_key_first($types);
    $discounts = collect($types)->contains(fn ($t) => $t['discount'] ?? false);
    $surface = $types[$first]['surface'];
    $editor = $store->adminUrl('themes/current/editor?template='.($surface === 'cart' ? 'cart' : ($surface === 'product' ? 'product' : 'index')).'&addAppBlockId='.config('shopify.api_key').'/experience&target=newAppsSection');
    $canManage = request()->attributes->get('storeUser')?->can('manage_experiences');
@endphp

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
@endpush

@section('content')
<s-page heading="{{ $feature['label'] }}">
    <x-app.hero :eyebrow="$feature['label']" :title="$feature['tagline']" :lead="$feature['lead']" :icon="$feature['icon'] ?? null" :tone="$feature['tone'] ?? null">
        @if ($canManage)
            @foreach ($types as $typeKey => $type)
                <s-button variant="{{ $loop->first ? 'primary' : 'secondary' }}" href="{{ app_route('app.cro.experiences.create', ['type' => $typeKey]) }}">Create {{ lower_label($type['singular']) }}</s-button>
            @endforeach
        @endif
        <s-button href="{{ $editor }}" target="_top">Open Theme Editor</s-button>
    </x-app.hero>

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
    <script>
        document.querySelectorAll('[data-render]').forEach((el) => {
            window.OrderOrbit.render(el, JSON.parse(el.dataset.render), { preview: true, currency: @json($store->currency ?? 'USD'), cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' });
        });
    </script>
@endpush
