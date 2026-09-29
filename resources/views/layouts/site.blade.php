<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'OrderOrbit | Shopify CRO, Checkout & Upsell App')</title>
    <meta name="description" content="@yield('description', 'Bundles, upsells, free gifts, shipping bars, checkout blocks, automation, analytics and A/B testing for Shopify — in one app.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
    <header class="site">
        <div class="wrap">
            <a class="logo" href="{{ route('site.home') }}">OrderOrbit</a>
            <nav class="main">
                <a class="hide-sm" href="{{ route('site.home') }}#features">Product</a>
                <a class="hide-sm" href="{{ route('site.home') }}#how-it-works">How It Works</a>
                <a class="hide-sm" href="{{ route('site.pricing') }}">Pricing</a>
                <a class="btn primary" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
            </nav>
        </div>
    </header>

    <main>@yield('content')</main>

    <footer class="site">
        <div class="wrap">
            <strong>OrderOrbit</strong> — Convert more customers. Increase order value. Bring customers back.
            <div class="cols">
                <div><h4>Product</h4><a href="{{ route('site.home') }}#features">Features</a><a href="{{ route('site.home') }}#how-it-works">How It Works</a><a href="{{ route('site.pricing') }}">Pricing</a></div>
                <div><h4>Legal</h4><a href="{{ route('site.privacy') }}">Privacy Policy</a><a href="{{ route('site.terms') }}">Terms</a></div>
            </div>
            <p>© {{ date('Y') }} OrderOrbit</p>
        </div>
    </footer>
</body>
</html>
