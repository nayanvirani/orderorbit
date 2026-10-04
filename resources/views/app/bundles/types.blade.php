@extends('layouts.embedded')

@section('title', 'Create bundle')

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
@endpush

@section('content')
<s-page inlineSize="large" heading="Step 1/2 · Select bundle type">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.bundles.index') }}">Bundles</s-link>
    <p class="bx-lead">Choose the bundle type that best suits your needs. Every type is fully customisable after you pick a model.</p>

    <div class="bx-types">
        @foreach ($types as $key => $type)
            <a class="bx-type" href="{{ app_route('app.bundles.models', ['type' => $key]) }}">
                <span class="bx-shot"><span class="bx-shot-inner oo-preview" data-render="{{ json_encode($type['preview']) }}"></span></span>
                <span class="bx-type-body">
                    <strong>{{ $type['label'] }}</strong>
                    <span class="bx-muted">{{ $type['lead'] }}</span>
                    <span class="bx-hint"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2a6 6 0 0 0-3.5 10.9V15h7v-2.1A6 6 0 0 0 10 2ZM7.5 17h5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>{{ $type['example'] }}</span>
                    <span class="bx-hint"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="10" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>{{ $type['goal'] }}</span>
                    <span class="bx-cta">Create →</span>
                </span>
            </a>
        @endforeach
        <a class="bx-type bx-type-link" href="{{ app_route('app.features.show', ['feature' => 'free-gifts']) }}">
            <span class="bx-type-body">
                <span class="bx-pill on">Module</span>
                <strong>Gift / discount with cart value</strong>
                <span class="bx-muted">Receive a discount or gift depending on the cart amount.</span>
                <span class="bx-hint">$50 = −10%. $100 = 1 gift.</span>
                <span class="bx-cta">Open free gifts →</span>
            </span>
        </a>
    </div>
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
