<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'OrderOrbit Space | Shopify CRO, Checkout & Upsell App')</title>
    <meta name="description" content="@yield('description', 'Bundles, progressive gifts, upsells, checkout blocks, automation, analytics and A/B testing for Shopify — in one app.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:site_name" content="OrderOrbit Space">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'OrderOrbit Space | Shopify CRO, Checkout & Upsell App')">
    <meta property="og:description" content="@yield('description', 'Bundles, progressive gifts, upsells, checkout blocks, automation, analytics and A/B testing for Shopify — in one app.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#ffffff">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@500&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mockups.css') }}?v={{ filemtime(public_path('css/mockups.css')) }}">
    @stack('head')
</head>
<body>
    @include('site.partials.icons')

    <header class="dock-wrap" data-header>
        <div class="dock">
            <a class="logo" href="{{ route('site.home') }}" aria-label="OrderOrbit Space home">
                <svg aria-hidden="true"><use href="#i-orbit"></use></svg>OrderOrbit Space
            </a>

            <nav class="nav" aria-label="Main">
                <div class="item mega-item" data-dropdown>
                    <button type="button" aria-expanded="false">Product <x-icon name="chev" class="chev"/></button>
                    <div class="dropdown mega">
                        <div class="col">
                            <h5>CRO</h5>
                            <div class="cro-list">
                                @foreach ($navGroups['convert'] as $slug => $f)
                                    <a class="dd-link" href="{{ route('site.feature', $slug) }}">
                                        <span class="ico"><x-icon :name="$f['icon']"/></span>
                                        <span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        <div class="col">
                            <h5>Checkout</h5>
                            @foreach ($navGroups['checkout'] as $slug => $f)
                                <a class="dd-link" href="{{ route('site.feature', $slug) }}">
                                    <span class="ico"><x-icon :name="$f['icon']"/></span>
                                    <span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span>
                                </a>
                            @endforeach
                            <a class="mega-feature" href="{{ route('site.templates') }}">
                                <strong>Start from a template</strong>
                                <span>87 ready-made designs to customise →</span>
                            </a>
                        </div>
                        <div class="col">
                            <h5>Grow</h5>
                            @foreach ($navGroups['grow'] as $slug => $f)
                                <a class="dd-link" href="{{ route('site.feature', $slug) }}">
                                    <span class="ico"><x-icon :name="$f['icon']"/></span>
                                    <span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="item" data-dropdown>
                    <button type="button" aria-expanded="false">Solutions <x-icon name="chev" class="chev"/></button>
                    <div class="dropdown narrow">
                        @foreach ($navSolutions as $slug => $s)
                            <a class="dd-link" href="{{ route('site.solution', $slug) }}">
                                <span class="ico"><x-icon :name="$s['icon']"/></span>
                                <span><strong>{{ $s['name'] }}</strong><span>{{ $s['tagline'] }}</span></span>
                            </a>
                        @endforeach
                        <a class="dd-link" href="{{ route('site.solutions') }}">
                            <span class="ico"><x-icon name="grid"/></span>
                            <span><strong>All solutions</strong><span>Find the setup that fits your store</span></span>
                        </a>
                    </div>
                </div>
                <div class="item"><a href="{{ route('site.templates') }}">Templates</a></div>
                <div class="item"><a href="{{ route('site.pricing') }}">Pricing</a></div>
                <div class="item" data-dropdown>
                    <button type="button" aria-expanded="false">Resources <x-icon name="chev" class="chev"/></button>
                    <div class="dropdown narrow">
                        <a class="dd-link" href="{{ route('site.how') }}"><span class="ico"><x-icon name="orbit"/></span><span><strong>How It Works</strong><span>From install to your first test</span></span></a>
                        <a class="dd-link" href="{{ route('site.resources') }}"><span class="ico"><x-icon name="book"/></span><span><strong>Resources</strong><span>Guides and templates</span></span></a>
                        <a class="dd-link" href="{{ route('site.blog') }}"><span class="ico"><x-icon name="message"/></span><span><strong>Blog</strong><span>Shopify conversion, explained</span></span></a>
                        <a class="dd-link" href="{{ route('site.help') }}"><span class="ico"><x-icon name="help"/></span><span><strong>Help Center</strong><span>Setup guides and troubleshooting</span></span></a>
                    </div>
                </div>
            </nav>

            <div class="header-actions">
                <a class="signin" href="{{ config('shopify.sign_in_url') }}">Sign In</a>
                <a class="btn primary" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install app</a>
                <button class="menu-toggle" type="button" aria-label="Menu" aria-expanded="false" data-menu-toggle><x-icon name="menu"/></button>
            </div>
        </div>
    </header>

    <div class="mobile-menu" data-mobile-menu>
        <details>
            <summary>Product <x-icon name="chev" class="chev"/></summary>
            <div class="links">
                @foreach ($navGroups as $group)
                    @foreach ($group as $slug => $f)
                        <a class="dd-link" href="{{ route('site.feature', $slug) }}"><span class="ico"><x-icon :name="$f['icon']"/></span><span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span></a>
                    @endforeach
                @endforeach
            </div>
        </details>
        <details>
            <summary>Solutions <x-icon name="chev" class="chev"/></summary>
            <div class="links">
                @foreach ($navSolutions as $slug => $s)
                    <a class="dd-link" href="{{ route('site.solution', $slug) }}"><span class="ico"><x-icon :name="$s['icon']"/></span><span><strong>{{ $s['name'] }}</strong><span>{{ $s['tagline'] }}</span></span></a>
                @endforeach
            </div>
        </details>
        <a class="plain" href="{{ route('site.templates') }}">Templates</a>
        <a class="plain" href="{{ route('site.pricing') }}">Pricing</a>
        <details>
            <summary>Resources <x-icon name="chev" class="chev"/></summary>
            <div class="links">
                <a class="dd-link" href="{{ route('site.how') }}"><span class="ico"><x-icon name="orbit"/></span><span><strong>How It Works</strong></span></a>
                <a class="dd-link" href="{{ route('site.resources') }}"><span class="ico"><x-icon name="book"/></span><span><strong>Resources</strong></span></a>
                <a class="dd-link" href="{{ route('site.blog') }}"><span class="ico"><x-icon name="message"/></span><span><strong>Blog</strong></span></a>
                <a class="dd-link" href="{{ route('site.help') }}"><span class="ico"><x-icon name="help"/></span><span><strong>Help Center</strong></span></a>
            </div>
        </details>
        <a class="plain" href="{{ config('shopify.sign_in_url') }}">Sign In</a>
        <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
    </div>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <div class="footer-top">
                <div class="brand">
                    <a class="logo" href="{{ route('site.home') }}"><svg aria-hidden="true"><use href="#i-orbit"></use></svg>OrderOrbit Space</a>
                    <p>Convert more customers. Increase order value. <em class="grad-text">Bring customers back.</em></p>
                    <div class="ctas" style="margin-top:24px">
                        <a class="btn primary" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
                    </div>
                </div>
                <div class="footer-cols">
                    <div>
                        <h4>Product</h4>
                        <a href="{{ route('site.features') }}">Features</a>
                        <a href="{{ route('site.templates') }}">Templates</a>
                        <a href="{{ route('site.how') }}">How It Works</a>
                        <a href="{{ route('site.pricing') }}">Pricing</a>
                    </div>
                    <div>
                        <h4>Solutions</h4>
                        @foreach ($navSolutions as $slug => $s)
                            <a href="{{ route('site.solution', $slug) }}">{{ str_replace(' Brands', '', $s['name']) }}</a>
                        @endforeach
                    </div>
                    <div>
                        <h4>Resources</h4>
                        <a href="{{ route('site.help') }}">Help Center</a>
                        <a href="{{ route('site.resources') }}">Guides</a>
                        <a href="{{ route('site.blog') }}">Blog</a>
                    </div>
                    <div>
                        <h4>Company</h4>
                        <a href="{{ route('site.about') }}">About</a>
                        <a href="{{ route('site.contact') }}">Contact</a>
                        <a href="{{ route('site.security') }}">Security</a>
                    </div>
                    <div>
                        <h4>Legal</h4>
                        <a href="{{ route('site.privacy') }}">Privacy Policy</a>
                        <a href="{{ route('site.terms') }}">Terms</a>
                        <a href="{{ route('site.dpa') }}">Data Processing</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© {{ date('Y') }} OrderOrbit Space. Built for Shopify.</span>
                <span>Placed in the Theme Editor · Checkout blocks · Consent-aware analytics</span>
            </div>
        </div>
        <div class="wordmark" aria-hidden="true">OrderOrbit Space</div>
    </footer>

    <script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
