<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="shopify-api-key" content="{{ config('shopify.api_key') }}">
    <title>@yield('title', 'OrderOrbit')</title>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
    <style>
        .oo-radio { display: flex; gap: 12px; align-items: flex-start; padding: 12px; border: 1px solid #e3e3e3; border-radius: 10px; margin-bottom: 8px; cursor: pointer; font: 14px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .oo-radio input { margin-top: 3px; }
        .oo-radio small { display: block; color: #616161; }
    </style>
</head>
<body>
    <s-app-nav>
        <s-link href="{{ app_route('app.dashboard') }}" rel="home">Home</s-link>
        <s-link href="{{ app_route('app.onboarding') }}">Onboarding</s-link>
        <s-link href="{{ app_route('app.billing') }}">Billing</s-link>
    </s-app-nav>

    @yield('content')

    <script>
        // Session tokens live for one minute, so attach a fresh one to every form post.
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            event.preventDefault();
            let input = form.querySelector('input[name="id_token"]');
            if (!input) {
                input = Object.assign(document.createElement('input'), { type: 'hidden', name: 'id_token' });
                form.appendChild(input);
            }
            input.value = await shopify.idToken();
            form.submit();
        });
    </script>
    @stack('scripts')
</body>
</html>
