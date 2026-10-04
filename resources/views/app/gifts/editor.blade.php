@extends('layouts.embedded')

@section('title', $experience->name)

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
@endpush

@section('content')
<s-page inlineSize="large" heading="{{ $experience->name }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.gifts.index') }}">Progressive gifts</s-link>

    @if ($banner)
        <s-banner tone="{{ $fieldErrors ? 'warning' : 'critical' }}">{{ $banner }}</s-banner>
    @endif
    @if ($experience->status === 'published' && $experience->has_unpublished_changes)
        <s-banner tone="info">These rewards have changes that aren't live yet. Publish to update your store.</s-banner>
    @endif

    <form method="POST" action="{{ app_route('app.gifts.update', ['gift' => $experience->id]) }}" class="bx-editor" data-gift-editor>
        <input type="hidden" name="config_json" data-config-json>
        <div class="bx-editor-top">
            <label class="bx-name">
                <span class="bx-muted">Name (only you see it)</span>
                <input name="name" value="{{ $experience->name }}" maxlength="120" required>
            </label>
            <div class="bx-status">@include('app.cro._status')</div>
        </div>

        <div class="bx-layout">
            <div class="bx-panel-col">
                <nav class="bx-tabbar" role="tablist" aria-label="Progressive gifts editor">
                    <button type="button" role="tab" data-tab="rewards" aria-selected="true">Rewards</button>
                    <button type="button" role="tab" data-tab="settings" aria-selected="false">Settings</button>
                    <button type="button" role="tab" data-tab="design" aria-selected="false">Design</button>
                </nav>
                <div data-panel="rewards"></div>
                <div data-panel="settings" hidden></div>
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
                    <label class="bx-preview-product"><span class="bx-muted" data-cart-label>Cart value</span>
                        <input type="range" min="0" max="200" value="60" data-cart-range style="flex:1">
                        <b data-cart-value></b>
                    </label>
                    <div class="b-stage">
                        <div class="b-frame" data-frame>
                            <div class="bx-fake-atc-wrap">
                                <span class="bx-fake-atc">Add to cart</span>
                                <div class="oo-root" data-preview></div>
                            </div>
                        </div>
                    </div>
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
        window.GiftEditor = {
            config: @json($config),
            errors: @json((object) $fieldErrors),
            meta: {
                samples: @json(\App\Services\Experiences\TemplateLibrary::samples()),
                id: @json($experience->handle),
                currency: @json($store->currency ?? 'USD'),
                timezone: @json($timezone),
                rewards: @json(\App\Experiences\GiftSchema::REWARDS),
                layouts: @json(\App\Experiences\GiftSchema::LAYOUTS),
            },
        };
    </script>
    <script src="{{ asset('js/gift-editor.js') }}?v={{ filemtime(public_path('js/gift-editor.js')) }}"></script>
@endpush
