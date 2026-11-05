<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="shopify-api-key" content="{{ config('shopify.api_key') }}">
    <title>Growvia</title>
    @include('partials.favicons')
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
    {{-- The admin's styles in one file (built by scripts/build-admin-css.mjs from the same files the Blade pages use). --}}
    <link rel="stylesheet" href="{{ asset('css/admin-app.css') }}?v={{ @filemtime(public_path('css/admin-app.css')) }}">
    @vite('main.jsx', 'spa')
    {{-- The first page's own code, fetched alongside the main script instead of after it. --}}
    @foreach (\App\Support\Spa\Page::preloads((string) ($page['component'] ?? '')) as $chunk)
        <link rel="modulepreload" href="{{ $chunk }}">
    @endforeach
    @php
        // Versioned so a new deploy is picked up: the storefront runtime and the checkout block likenesses.
        $assets = [
            'runtime' => route('storefront.asset', 'orderorbit.js', false).'?v='.@filemtime(base_path('extensions/orderorbit-theme/assets/orderorbit.js')),
            'runtimeCss' => route('storefront.asset', 'orderorbit.css', false).'?v='.@filemtime(base_path('extensions/orderorbit-theme/assets/orderorbit.css')),
            'checkoutPreview' => '/js/checkout-preview.js?v='.@filemtime(public_path('js/checkout-preview.js')),
        ];
    @endphp
    <script>
        window.OO_PAGE = @json($page);
        window.OO_ROUTES = @json(\App\Support\Spa\Shared::routes());
        window.OO_ASSETS = @json($assets);
        window.OO_SAMPLE_IMAGE = @json(\App\Services\Experiences\TemplateLibrary::samples()[0]['image']);
    </script>
</head>
<body>
    @include('app._appnav')
    {{-- The React admin (resources/app) renders here; later pages load as JSON without reloading. --}}
    <div id="root"></div>
    @if ($notice = \App\Support\Notices::get(request('notice')))
        <script>
            shopify.toast.show(@json($notice[0]), { isError: @json($notice[1]) });
            (() => { const u = new URL(location.href); u.searchParams.delete('notice'); history.replaceState(null, '', u); })();
        </script>
    @endif
</body>
</html>
