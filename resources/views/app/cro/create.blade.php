@extends('layouts.embedded')

@section('title', 'Create experience')

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
@endpush

@section('content')
<s-page heading="Create experience">
    <x-app.hero eyebrow="New experience" :title="$type ? 'Choose a <em>'.e(lower_label(\App\Experiences\Registry::type($type)['singular'])).'</em> template.' : 'What do you want to <em>build?</em>'"
        :lead="$type ? (\App\Experiences\Registry::type($type)['surface'] === 'account' ? 'Pick a layout, then set the content and style. Customer accounts use your checkout branding.' : (in_array(\App\Experiences\Registry::type($type)['surface'], \App\Experiences\Schema::CHECKOUT_SURFACES, true) ? 'Pick a layout, then set the content, cart-value and country conditions. Checkout uses your checkout branding.' : 'Every template is fully customisable — content, design, targeting and schedule.')) : 'Pick a feature. Every one goes live on your storefront, and savings apply automatically at checkout.'" />
    <div class="b-steps" aria-label="Steps">
        <span class="b-step" aria-current="{{ $type ? 'false' : 'step' }}"><span>1</span>Type</span>
        <span class="b-step" aria-current="{{ $type ? 'step' : 'false' }}"><span>2</span>Template</span>
        <span class="b-step"><span>3</span>Configure</span>
    </div>

    @if (! $type)
        <s-section>
            <div class="ob-types">
                @foreach (\App\Experiences\Registry::creatable() as $key => $t)
                    <a class="ob-type" href="{{ app_route('app.cro.experiences.create', ['type' => $key]) }}">
                        <h4>{{ $t['label'] }}</h4>
                        <p>{{ $t['description'] }}</p>
                        <div class="ob-row"><span style="color:var(--ob-sun)">Choose →</span></div>
                    </a>
                @endforeach
            </div>
        </s-section>
    @else
        @php($t = \App\Experiences\Registry::type($type))
        <s-section>
            <form method="POST" action="{{ app_route('app.cro.experiences.store') }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="b-templates" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
                    @foreach ($previews as $preview)
                        <label class="b-template">
                            <input type="radio" name="template" value="{{ $preview['template'] }}" @checked(request('template') ? request('template') === $preview['template'] : $loop->first) required>
                            <span class="b-template-preview oo-preview" style="zoom:.8;max-height:260px" data-render="{{ json_encode($preview) }}"></span>
                            <span class="b-template-name">{{ $t['templates'][$preview['template']]['name'] }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="oo-form-row" style="margin-top:16px">
                    <label class="oo-field" style="flex:1 1 260px">Internal name (optional)<input name="name" maxlength="120" placeholder="{{ $t['singular'] }} · {{ reset($t['templates'])['name'] }}"></label>
                    <s-button type="submit" variant="primary">Continue</s-button>
                    <s-button href="{{ app_route('app.cro.experiences.create') }}">Back</s-button>
                </div>
            </form>
        </s-section>
    @endif
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
