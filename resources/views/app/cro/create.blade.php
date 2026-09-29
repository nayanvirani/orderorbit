@extends('layouts.embedded')

@section('title', 'Create experience')

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
@endpush

@section('content')
<s-page heading="Create experience">
    <div class="b-steps" aria-label="Steps">
        <span class="b-step" aria-current="{{ $type ? 'false' : 'step' }}"><span>1</span>Type</span>
        <span class="b-step" aria-current="{{ $type ? 'step' : 'false' }}"><span>2</span>Template</span>
        <span class="b-step"><span>3</span>Configure</span>
    </div>

    @if (! $type)
        <s-section heading="What do you want to build?">
            <s-grid gridTemplateColumns="repeat(auto-fit, minmax(220px, 1fr))" gap="base">
                @foreach (\App\Experiences\Registry::types() as $key => $t)
                    <s-box padding="base" border="base" borderRadius="base">
                        <s-stack gap="small-200">
                            <s-stack direction="inline" gap="small-200" alignItems="center"><strong>{{ $t['label'] }}</strong>@unless ($t['publishable'])<s-badge tone="info">Preview</s-badge>@endunless</s-stack>
                            <s-text color="subdued">{{ $t['description'] }}</s-text>
                            <s-button href="{{ app_route('app.cro.experiences.create', ['type' => $key]) }}">Choose</s-button>
                        </s-stack>
                    </s-box>
                @endforeach
            </s-grid>
        </s-section>
    @else
        @php($t = \App\Experiences\Registry::type($type))
        <s-section heading="Choose a {{ strtolower($t['singular']) }} template">
            <form method="POST" action="{{ app_route('app.cro.experiences.store') }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="b-templates" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
                    @foreach ($previews as $preview)
                        <label class="b-template">
                            <input type="radio" name="template" value="{{ $preview['template'] }}" @checked($loop->first) required>
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
    <script>
        document.querySelectorAll('[data-render]').forEach((el) => {
            window.OrderOrbit.render(el, JSON.parse(el.dataset.render), { preview: true, currency: @json($store->currency ?? 'USD'), cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' });
        });
    </script>
@endpush
