@extends('layouts.embedded')

@section('title', 'Choose your model')

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
@endpush

@section('content')
<s-page heading="Step 2/2 · Choose your model">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.bundles.types') }}">Bundle types</s-link>
    <p class="bx-lead">{{ $type['label'] }}: {{ $type['lead'] }} Choose a ready-made model, then customise everything.</p>

    <div class="bx-filters" data-model-filters>
        <div class="bx-filter-row">
            <strong>Filters and preview</strong>
            <div class="bx-seg" role="group" aria-label="Layout">
                @foreach (['all' => 'All', 'vertical' => 'Vertical', 'horizontal' => 'Horizontal', 'grid' => 'Grid'] as $key => $label)
                    <button type="button" data-layout="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="bx-swatches" role="group" aria-label="Colour">
                @foreach ($presets as $key => $preset)
                    <button type="button" class="bx-swatch" data-preset="{{ $key }}" style="--sw: {{ $preset['accent'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-label="{{ ucfirst($key) }}"></button>
                @endforeach
            </div>
        </div>
        <div class="bx-filter-row">
            <strong>Preview product (optional)</strong>
            <button type="button" class="b-btn" data-preview-product>Select product</button>
            <span class="bx-muted" data-preview-product-name>Sample product</span>
        </div>
    </div>

    <div class="bx-models">
        @foreach ($models as $key => $model)
            <div class="bx-model" data-layout="{{ $model['layout'] }}">
                <div class="bx-model-head"><strong>{{ $model['name'] }}</strong></div>
                <p class="bx-muted">{{ $model['description'] }}</p>
                <div class="bx-model-shot">
                    <div class="oo-preview" data-model="{{ $key }}" data-previews="{{ json_encode($model['previews']) }}"></div>
                </div>
                <form method="POST" action="{{ app_route('app.bundles.store') }}">
                    <input type="hidden" name="model" value="{{ $key }}">
                    <input type="hidden" name="preset" value="black" data-preset-input>
                    <button type="submit" class="b-btn b-primary bx-use">Use this template</button>
                </form>
            </div>
        @endforeach
    </div>
</s-page>
@endsection

@push('scripts')
    <script src="{{ route('storefront.asset', 'orderorbit.js') }}"></script>
    <script>
        (() => {
            const ctx = { preview: true, currency: @json($store->currency ?? 'USD'), cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' };
            let preset = 'black';
            const draw = () => document.querySelectorAll('[data-model]').forEach((el) => {
                window.OrderOrbit.render(el, JSON.parse(el.dataset.previews)[preset], Object.assign({}, ctx));
            });
            document.querySelectorAll('[data-preset]').forEach((b) => b.addEventListener('click', () => {
                preset = b.dataset.preset;
                document.querySelectorAll('[data-preset]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
                document.querySelectorAll('[data-preset-input]').forEach((i) => { i.value = preset; });
                draw();
            }));
            document.querySelectorAll('[data-layout]').forEach((b) => b.tagName === 'BUTTON' && b.addEventListener('click', () => {
                document.querySelectorAll('button[data-layout]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
                document.querySelectorAll('.bx-model').forEach((m) => { m.hidden = b.dataset.layout !== 'all' && m.dataset.layout !== b.dataset.layout; });
            }));
            // Preview with one of the store's products (title, price, image, variants).
            document.querySelector('[data-preview-product]').addEventListener('click', async () => {
                if (!window.shopify || !shopify.resourcePicker) return;
                const picked = await shopify.resourcePicker({ type: 'product', multiple: false });
                const p = picked && picked[0];
                if (!p) return;
                const variants = (p.variants || []).map((v) => ({ id: v.id, title: v.title, price: Number(v.price), available: true }));
                Object.assign(ctx, {
                    productTitle: p.title,
                    productPrice: Math.round(Number((p.variants[0] || {}).price || 29) * 100),
                    productImage: (p.images && p.images[0] && (p.images[0].originalSrc || p.images[0].url)) || null,
                    pageProduct: { title: p.title, price: Number((p.variants[0] || {}).price || 29), variants: variants.length > 1 ? variants : null, image: (p.images && p.images[0] && (p.images[0].originalSrc || p.images[0].url)) || null },
                });
                document.querySelector('[data-preview-product-name]').textContent = p.title;
                draw();
            });
            draw();
        })();
    </script>
@endpush
