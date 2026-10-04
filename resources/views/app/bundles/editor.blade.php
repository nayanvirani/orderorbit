@extends('layouts.embedded')

@section('title', $experience->name)

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
@endpush

@section('content')
<s-page inlineSize="large" heading="{{ $experience->name }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.bundles.index') }}">Bundles</s-link>

    @if ($banner)
        <s-banner tone="{{ $fieldErrors ? 'warning' : 'critical' }}">{{ $banner }}</s-banner>
    @endif
    @if ($experience->status === 'published' && $experience->has_unpublished_changes)
        <s-banner tone="info">This bundle has changes that aren't live yet. Publish to update your store.</s-banner>
    @endif

    <form method="POST" action="{{ app_route('app.bundles.update', ['bundle' => $experience->id]) }}" id="bundle-editor" class="bx-editor" data-bundle-editor>
        <input type="hidden" name="config_json" data-config-json>

        <div class="bx-editor-top">
            <label class="bx-name">
                <span class="bx-muted">Bundle name (only you see it)</span>
                <input name="name" value="{{ $experience->name }}" maxlength="120" required>
            </label>
            <div class="bx-status">
                @include('app.cro._status')
                <span class="bx-pill">{{ $type['label'] }}</span>
            </div>
        </div>

        <div class="bx-layout">
            <div class="bx-panel-col">
                <div class="bx-card bx-inline-card" data-schedule></div>

                <nav class="bx-tabbar" role="tablist" aria-label="Bundle editor">
                    <button type="button" role="tab" data-tab="settings" aria-selected="true">Settings</button>
                    <button type="button" role="tab" data-tab="offers" aria-selected="false">{{ $config['bundle_type'] === 'mix-match' ? 'Products' : 'Offers' }}</button>
                    <button type="button" role="tab" data-tab="design" aria-selected="false">Design</button>
                </nav>
                <div data-panel="settings"></div>
                <div data-panel="offers" hidden></div>
                <div data-panel="design" hidden></div>
            </div>

            <aside class="bx-preview-col">
                <div class="bx-card bx-preview">
                    <div class="bx-preview-head">
                        <strong>Preview</strong>
                        <div class="b-seg" role="group" aria-label="Preview device">
                            <button type="button" data-device="desktop" aria-pressed="true">Desktop</button>
                            <button type="button" data-device="mobile" aria-pressed="false">Mobile</button>
                        </div>
                    </div>
                    <div class="bx-preview-product">
                        <span class="bx-muted">Product</span>
                        <button type="button" class="b-btn" data-preview-product>Sample product</button>
                    </div>
                    <div class="b-stage">
                        <div class="b-frame" data-frame>
                            <div class="bx-fake-atc-wrap">
                                <div class="oo-root" data-preview></div>
                                <span class="bx-fake-atc">Theme add to cart</span>
                            </div>
                        </div>
                    </div>
                    <p class="bx-muted bx-small">Live preview with sample prices. On your store, prices and variants come from Shopify.</p>
                </div>
                <div class="bx-savebar">
                    <span class="b-dirty" data-dirty hidden>Unsaved changes</span>
                    <button type="submit" name="action" value="save" class="b-btn">Save as draft</button>
                    <button type="submit" name="action" value="publish" class="b-btn b-primary">{{ $experience->status === 'published' ? 'Publish changes' : 'Publish' }}</button>
                </div>
            </aside>
        </div>
    </form>
</s-page>
@endsection

@push('scripts')
    <script src="{{ route('storefront.asset', 'orderorbit.js') }}"></script>
    <script>
        window.BundleEditor = {
            config: @json($config),
            errors: @json((object) $fieldErrors),
            meta: {
                samples: @json(\App\Services\Experiences\TemplateLibrary::samples()),
                id: @json($experience->handle),
                currency: @json($store->currency ?? 'USD'),
                timezone: @json($timezone),
                offerKinds: @json(array_intersect_key(\App\Experiences\BundleSchema::OFFER_KINDS, array_flip($type['offer_kinds']))),
                discounts: @json(\App\Experiences\BundleSchema::DISCOUNTS),
                presets: @json(\App\Experiences\BundleSchema::PRESETS),
                designDefaults: @json(\App\Experiences\BundleSchema::designDefaults()),
            },
        };
    </script>
    <script src="{{ asset('js/bundle-editor.js') }}?v={{ filemtime(public_path('js/bundle-editor.js')) }}"></script>
@endpush
