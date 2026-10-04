<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="shopify-api-key" content="{{ config('shopify.api_key') }}">
    <title>OrderOrbit Space</title>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
    <link rel="stylesheet" href="{{ asset('spa/app.css') }}?v={{ @filemtime(public_path('spa/app.css')) }}">
    @php
        $store = request()->attributes->get('store');
        $boot = [
            'shop' => $store->shop_domain,
            'storeName' => $store->name,
            'userName' => request()->attributes->get('storeUser')?->first_name,
            'plan' => $store->effectivePlan(),
            'currency' => $store->currency ?? 'USD',
        ];
    @endphp
    <script>window.OO_BOOT = @json($boot);</script>
</head>
<body>
    @include('app._appnav')
    @include('app._sales_limit')
    {{-- The React admin (resources/app) renders here and loads each card's data in the background. --}}
    <div id="root"></div>
    <script type="module" src="{{ asset('spa/app.js') }}?v={{ @filemtime(public_path('spa/app.js')) }}"></script>
    @if ($notice = \App\Support\Notices::get(request('notice')))
        <script>
            shopify.toast.show(@json($notice[0]), { isError: @json($notice[1]) });
            (() => { const u = new URL(location.href); u.searchParams.delete('notice'); history.replaceState(null, '', u); })();
        </script>
    @endif
</body>
</html>
