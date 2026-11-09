<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('js');</script>
    <title>@yield('title', 'Growvia | Shopify CRO, Checkout & Upsell App')</title>
    <meta name="description" content="@yield('description', 'Bundles, progressive gifts, upsells, checkout blocks, automation, analytics and A/B testing for Shopify — in one app.')">
    <link rel="canonical" href="{{ url()->current() }}">
    {{-- Crawler rules from the Internal Admin (Crawlers & SEO), with /robots.txt. --}}
    <meta name="robots" content="{{ \App\Support\Crawlers::robotsTag() }}">
    <meta property="og:site_name" content="Growvia">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'Growvia | Shopify CRO, Checkout & Upsell App')">
    <meta property="og:description" content="@yield('description', 'Bundles, progressive gifts, upsells, checkout blocks, automation, analytics and A/B testing for Shopify — in one app.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og/growvia.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Growvia: bundles, upsells and real profit for Shopify">
    <meta property="og:locale" content="en_US">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Growvia | Shopify CRO, Checkout & Upsell App')">
    <meta name="twitter:description" content="@yield('description', 'Bundles, progressive gifts, upsells, checkout blocks, automation, analytics and A/B testing for Shopify — in one app.')">
    <meta name="twitter:image" content="{{ asset('images/og/growvia.png') }}">
    @php
        // Breadcrumbs for Google from the URL: Home › section › this page (the title without the brand).
        $crumbPath = trim(request()->path(), '/');
        $crumbSections = ['features' => 'Features', 'solutions' => 'Solutions', 'blog' => 'Blog', 'docs' => 'Docs', 'legal' => 'Legal'];
        $crumbs = [['Home', route('site.home')]];
        if ($crumbPath !== '') {
            $parts = explode('/', $crumbPath);
            if (count($parts) > 1 && isset($crumbSections[$parts[0]])) {
                $crumbs[] = [$crumbSections[$parts[0]], url($parts[0])];
            }
            $crumbs[] = [trim(preg_replace('/\s*\|[^|]*$/', '', html_entity_decode(trim($__env->yieldContent('title')), ENT_QUOTES))) ?: 'Growvia', url()->current()];
        }
    @endphp
    @if (count($crumbs) > 1)
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]])->all()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    <meta name="theme-color" content="{{ \App\Support\SiteTheme::get()['header_bg'] }}">
    @include('partials.favicons')
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mockups.css') }}?v={{ filemtime(public_path('css/mockups.css')) }}">
    @include('site.partials.theme')
    @stack('head')
</head>
<body>
    @include('site.partials.icons')
    @php($menu = $site['menu'])

    <header class="site-header" data-header>
        <div class="wrap header-inner">
            <a class="logo" href="{{ route('site.home') }}" aria-label="Growvia home">
                <svg aria-hidden="true" viewBox="0 0 100 100"><use href="#i-growvia"></use></svg>Growvia
            </a>

            <nav class="nav" aria-label="Main">
                <div class="item mega-item" data-dropdown>
                    <button type="button" aria-expanded="false">{{ $menu['product'] }} <x-icon name="chev" class="chev"/></button>
                    <div class="dropdown mega">
                        <div>
                            <h5>{{ $site['product_menu']['convert'] }}</h5>
                            <div class="cro-list">
                                @foreach ($navGroups['convert'] as $slug => $f)
                                    <a class="dd-link" href="{{ route('site.feature', $slug) }}"><span class="ico"><x-icon :name="$f['icon']"/></span><span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span></a>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <h5>{{ $site['product_menu']['checkout'] }}</h5>
                            @foreach ($navGroups['checkout'] as $slug => $f)
                                <a class="dd-link" href="{{ route('site.feature', $slug) }}"><span class="ico"><x-icon :name="$f['icon']"/></span><span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span></a>
                            @endforeach
                            <a class="mega-feature" href="{{ route('site.templates') }}">
                                <strong>{{ site_md($site['product_menu']['templates_title']) }}</strong>
                                <span>{{ site_md($site['product_menu']['templates_text']) }}</span>
                            </a>
                        </div>
                        <div>
                            <h5>{{ $site['product_menu']['grow'] }}</h5>
                            @foreach ($navGroups['grow'] as $slug => $f)
                                <a class="dd-link" href="{{ route('site.feature', $slug) }}"><span class="ico"><x-icon :name="$f['icon']"/></span><span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span></a>
                            @endforeach
                            @if (! empty($navGroups['store']))
                                <h5 style="margin-top:14px">{{ $site['product_menu']['store'] ?? 'Profit & store health' }}</h5>
                                @foreach ($navGroups['store'] as $slug => $f)
                                    <a class="dd-link" href="{{ route('site.feature', $slug) }}"><span class="ico"><x-icon :name="$f['icon']"/></span><span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span></a>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
                <div class="item" data-dropdown>
                    <button type="button" aria-expanded="false">{{ $menu['solutions'] }} <x-icon name="chev" class="chev"/></button>
                    <div class="dropdown">
                        @foreach ($navSolutions as $slug => $s)
                            <a class="dd-link" href="{{ route('site.solution', $slug) }}"><span class="ico"><x-icon :name="$s['icon']"/></span><span><strong>{{ $s['name'] }}</strong><span>{{ $s['tagline'] }}</span></span></a>
                        @endforeach
                        <a class="dd-link" href="{{ route('site.solutions') }}"><span class="ico"><x-icon name="grid"/></span><span><strong>{{ $site['solutions_menu']['all_title'] }}</strong><span>{{ $site['solutions_menu']['all_text'] }}</span></span></a>
                    </div>
                </div>
                <div class="item"><a href="{{ route('site.templates') }}">{{ $menu['templates'] }}</a></div>
                <div class="item"><a href="{{ route('site.pricing') }}">{{ $menu['pricing'] }}</a></div>
                <div class="item" data-dropdown>
                    <button type="button" aria-expanded="false">{{ $menu['resources'] }} <x-icon name="chev" class="chev"/></button>
                    <div class="dropdown">
                        @foreach ($site['resources_menu'] as $link)
                            <a class="dd-link" href="{{ site_url($link['href']) }}"><span class="ico"><x-icon :name="['book', 'help', 'layers', 'message', 'orbit', 'grid'][$loop->index % 6]"/></span><span><strong>{{ $link['label'] }}</strong><span>{{ $link['text'] }}</span></span></a>
                        @endforeach
                    </div>
                </div>
            </nav>

            <div class="header-actions">
                <a class="signin" href="{{ config('shopify.sign_in_url') }}">{{ $menu['signin'] }}</a>
                <a class="btn primary" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">{{ $menu['install'] }}</a>
                <button class="menu-toggle" type="button" aria-label="Menu" aria-expanded="false" aria-controls="mobile-menu" data-menu-toggle><x-icon name="menu" class="when-closed"/><x-icon name="x" class="when-open"/></button>
            </div>
        </div>
    </header>

    <div class="mobile-menu" id="mobile-menu" data-mobile-menu>
        <details>
            <summary>{{ $menu['product'] }} <x-icon name="chev"/></summary>
            <div class="links">
                @foreach ($navGroups as $group)
                    @foreach ($group as $slug => $f)
                        <a class="dd-link" href="{{ route('site.feature', $slug) }}"><span class="ico"><x-icon :name="$f['icon']"/></span><span><strong>{{ $f['name'] }}</strong><span>{{ $f['menu'] }}</span></span></a>
                    @endforeach
                @endforeach
            </div>
        </details>
        <details>
            <summary>{{ $menu['solutions'] }} <x-icon name="chev"/></summary>
            <div class="links">
                @foreach ($navSolutions as $slug => $s)
                    <a class="dd-link" href="{{ route('site.solution', $slug) }}"><span class="ico"><x-icon :name="$s['icon']"/></span><span><strong>{{ $s['name'] }}</strong><span>{{ $s['tagline'] }}</span></span></a>
                @endforeach
            </div>
        </details>
        <a class="plain" href="{{ route('site.templates') }}">{{ $menu['templates'] }}</a>
        <a class="plain" href="{{ route('site.pricing') }}">{{ $menu['pricing'] }}</a>
        <details>
            <summary>{{ $menu['resources'] }} <x-icon name="chev"/></summary>
            <div class="links">
                @foreach ($site['resources_menu'] as $link)
                    <a class="dd-link" href="{{ site_url($link['href']) }}"><span><strong>{{ $link['label'] }}</strong><span>{{ $link['text'] }}</span></span></a>
                @endforeach
            </div>
        </details>
        <a class="plain" href="{{ config('shopify.sign_in_url') }}">{{ $menu['signin'] }}</a>
        <a class="btn primary" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">{{ $menu['install_mobile'] }}</a>
    </div>

    <main>
        @yield('content')
    </main>

    @php($footer = $site['footer'])
    <footer class="site-footer">
        <div class="wrap">
            <div class="top">
                <div class="brand">
                    <a class="logo" href="{{ route('site.home') }}"><svg aria-hidden="true" viewBox="0 0 100 100"><use href="#i-growvia"></use></svg>Growvia</a>
                    <p>{{ site_md($footer['tagline']) }}</p>
                    @if ($footer['button']['label'])<a class="btn light" href="{{ site_url($footer['button']['href']) }}" data-event="cta_install_clicked">{{ $footer['button']['label'] }}</a>@endif
                </div>
                <div class="cols">
                    @foreach ($footer['columns'] as $column)
                        <div>
                            <h4>{{ $column['title'] }}</h4>
                            @foreach ($column['links'] as $link)<a href="{{ site_url($link['href']) }}">{{ $link['label'] }}</a>@endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            @php($footerLegal = array_filter(\App\Support\Legal::pages(), fn ($p) => $p['footer']))
            <nav class="footer-legal" aria-label="Legal">
                @forelse ($footerLegal as $legal)
                    <a href="{{ \App\Support\Legal::url($legal['slug']) }}">{{ $legal['title'] }}</a>
                @empty
                    <a href="{{ route('site.privacy') }}">Privacy Policy</a>
                    <a href="{{ route('site.terms') }}">Terms of Service</a>
                @endforelse
            </nav>
            <div class="footer-bottom">
                <span>{{ site_md($footer['copyright']) }}</span>
                <span>{{ site_md($footer['note']) }}</span>
            </div>
        </div>
    </footer>

    <script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
