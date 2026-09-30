@extends('layouts.embedded')

@section('title', 'Choose a template')

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
@endpush

@section('content')
<s-page heading="Choose a template">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.gifts.index') }}">Progressive gifts</s-link>
    <p class="bx-lead">Start from a ready-made layout. Rewards, thresholds, colours and text are all yours to change.</p>

    @foreach ($groups as $group => $models)
        <h2 class="bx-group">{{ $group }}</h2>
        <div class="bx-models">
            @foreach ($models as $model)
                <div class="bx-model">
                    <div class="bx-model-shot bx-pdp">
                        <span class="bx-pdp-line"></span><span class="bx-pdp-line short"></span>
                        <span class="bx-fake-atc">Add to cart</span>
                        <div class="oo-preview" data-render="{{ json_encode($model['preview']) }}"></div>
                    </div>
                    <div class="bx-model-head"><strong>{{ $model['name'] }}</strong></div>
                    <p class="bx-muted">{{ $model['description'] }}</p>
                    <form method="POST" action="{{ app_route('app.gifts.store') }}">
                        <input type="hidden" name="model" value="{{ $model['key'] }}">
                        <button type="submit" class="b-btn bx-use">Use this template</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endforeach
</s-page>
@endsection

@push('scripts')
    <script src="{{ route('storefront.asset', 'orderorbit.js') }}"></script>
    <script>
        document.querySelectorAll('[data-render]').forEach((el) => {
            window.OrderOrbit.render(el, JSON.parse(el.dataset.render), { preview: true, currency: @json($store->currency ?? 'USD'), cartTotal: 6000, page: 'product' });
        });
    </script>
@endpush
