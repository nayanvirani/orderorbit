@extends('layouts.embedded')

@section('title', 'Templates')

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
@endpush

@section('content')
<s-page heading="Templates">
    <x-app.hero eyebrow="Template library" title="Start from a <em>proven template.</em>"
        lead="Every template shares your brand colours and fonts. Pick one, customise it in the builder, and publish it from the Theme Editor." />
    <nav class="oo-tabs" aria-label="Template types">
        <a href="{{ app_route('app.cro.templates') }}" @if (! $type) aria-current="page" @endif>All</a>
        @foreach (\App\Experiences\Registry::creatable() as $key => $t)
            <a href="{{ app_route('app.cro.templates', ['type' => $key]) }}" @if ($type === $key) aria-current="page" @endif>{{ $t['label'] }}</a>
        @endforeach
    </nav>

    <div class="b-templates" style="grid-template-columns:repeat(auto-fill,minmax(270px,1fr))">
        @foreach ($templates as $template)
            <div class="b-template" style="cursor:default">
                <span class="b-template-preview oo-preview" data-render="{{ json_encode($template['preview']) }}"></span>
                <span class="b-template-name">{{ $template['name'] }}</span>
                <span class="oo-inline oo-small">
                    <s-badge>{{ $template['type_label'] }}</s-badge>
                    <span class="oo-muted">v{{ $template['version'] }} · {{ ['product' => 'Product page', 'cart' => 'Cart', 'any' => 'Any page'][$template['surface']] ?? $template['surface'] }}</span>
                    @if ($template['used_by'])<span class="oo-muted">· used by {{ $template['used_by'] }}</span>@endif
                </span>
                @if (request()->attributes->get('storeUser')?->can('manage_experiences'))
                    <form method="POST" action="{{ app_route('app.cro.experiences.store') }}">
                        <input type="hidden" name="type" value="{{ $template['type'] }}">
                        <input type="hidden" name="template" value="{{ $template['key'] }}">
                        <s-button type="submit">Use template</s-button>
                    </form>
                @endif
            </div>
        @endforeach
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
