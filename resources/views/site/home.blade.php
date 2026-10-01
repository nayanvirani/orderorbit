@extends('layouts.site')

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'OrderOrbit Space',
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Shopify',
    'description' => 'Bundles, progressive gifts, upsells, countdowns and analytics for Shopify, in one app.',
    'offers' => array_values(array_map(fn ($p) => ['@type' => 'Offer', 'name' => $p['name'], 'price' => $p['price'], 'priceCurrency' => 'USD'], $plans)),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@php
    $features = collect(\App\Support\Content::features())->sortBy(fn ($f) => ($f['status'] ?? 'live') === 'soon' ? 1 : 0)->all();
@endphp

@section('content')
<div class="mn">
    <section class="mn-hero center">
        <div class="wrap">
            <span class="mn-kicker">A Shopify app for higher order value</span>
            <h1>Sell more to every shopper, <em>without fighting your theme.</em></h1>
            <p class="mn-lead">Bundles, progressive gifts, cart upsells, countdowns and trust blocks that look like part of your store, apply their savings at checkout, and show you exactly what they earn.</p>
            <div class="ctas">
                <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
                <a class="btn lg" href="{{ route('site.how') }}">How it works</a>
            </div>
        </div>
    </section>

    @php
        $sats = [
            ['bundles', 'bundle', 'Product page', 'p1'],
            ['progressive-gifts', 'gift', 'Product & cart', 'p2'],
            ['countdown-timer', 'countdown', 'Any page', 'p3'],
            ['cart-upsells', 'upsell', 'Cart', 'p4'],
            ['trust-social-proof', 'trust', 'Product page', 'p5'],
            ['sticky-add-to-cart', 'sticky', 'Product page', 'p6'],
            ['analytics', 'analytics', 'In the app', 'p7'],
        ];
        $soonFeatures = array_filter($features, fn ($f) => ($f['status'] ?? 'live') === 'soon');
    @endphp
    <section class="mn-orbit" aria-label="Every OrderOrbit Space feature">
        <div class="ring r1"></div>
        <div class="ring r2"></div>
        <div class="planet">
            <span class="planet-name">OrderOrbit Space</span>
            <span class="planet-sub">{{ count($features) - count($soonFeatures) }} features live · {{ count($soonFeatures) }} on the way</span>
            <div class="soon-pills">
                @foreach ($soonFeatures as $slug => $f)
                    <a href="{{ route('site.feature', $slug) }}" style="animation-delay:-{{ $loop->index * 1.3 }}s"><x-icon :name="$f['icon']"/>{{ $f['name'] }}<em>Soon</em></a>
                @endforeach
            </div>
        </div>
        @foreach ($sats as [$slug, $type, $where, $pos])
            <a class="osat {{ $pos }}" href="{{ route('site.feature', $slug) }}" style="animation-delay:-{{ $loop->index * 1.1 }}s">
                <span class="osat-label"><b>{{ $features[$slug]['name'] }}</b><span>{{ $where }}</span></span>
                @if ($type === 'analytics')
                    <div class="mini-kpi">
                        <div><span>Revenue from offers</span><b>$4,280</b><em>▲ 18%</em></div>
                        <div class="bars">@foreach ([38, 52, 44, 63, 58, 76, 88] as $h)<i style="height:{{ $h }}%"></i>@endforeach</div>
                    </div>
                @else
                    @include('site.partials.thumb', ['type' => $type, 'v' => $type === 'trust' ? 0 : 1])
                @endif
            </a>
        @endforeach
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Replace 5–10 apps with one</h2>
            <p class="mn-intro">A typical Shopify store adds a separate app for every job: bundles, gifts, a shipping bar, upsells, a timer, a sticky button, trust badges, reporting. Each one has its own bill, its own script and its own look. OrderOrbit Space does all of these jobs in one app.</p>
            <div class="mn-compare">
                <div class="col">
                    <p class="mn-group">Separate apps</p>
                    <ul>
                        @foreach ([
                            ['Bundles & quantity breaks', '$10–40'],
                            ['Free gift with purchase', '$10–30'],
                            ['Free shipping bar', '$5–15'],
                            ['Cart upsells', '$10–40'],
                            ['Countdown timer', '$5–15'],
                            ['Sticky add to cart', '$5–15'],
                            ['Trust badges & reviews widget', '$5–20'],
                            ['Revenue analytics', '$20–50'],
                        ] as [$job, $cost])
                            <li><span>{{ $job }}</span><b>{{ $cost }}</b></li>
                        @endforeach
                    </ul>
                    <p class="total"><span>8 apps, every month</span><b>$70–225</b></p>
                </div>
                <div class="col ours">
                    <p class="mn-group">OrderOrbit Space</p>
                    <p class="big">1 app</p>
                    <p class="price">from <b>${{ number_format(min(array_column($plans, 'price')), 2) }}</b> a month</p>
                    <p>Every live feature is included on every plan. Higher plans raise the limits, not the feature list.</p>
                    <a class="btn" href="{{ route('site.pricing') }}">See pricing</a>
                </div>
            </div>
            <p class="mn-fine">Separate-app prices are typical monthly ranges for single-purpose apps on the Shopify App Store; your own bills may differ.</p>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>What changes after you install</h2>
            <ul class="mn-list">
                <li><b>You pay for one app, not eight</b><span>One subscription on your Shopify invoice replaces a stack of monthly bills — and one place to manage every offer.</span></li>
                <li><b>Your store loads faster</b><span>Every extra app adds its own scripts and styles to your pages. OrderOrbit Space uses one small shared runtime, and each feature loads only on pages where it appears.</span></li>
                <li><b>No more app conflicts</b><span>Separate apps fight over your add-to-cart button and cart. Here every feature is built to work together, and none of them edit theme code.</span></li>
                <li><b>One look across the store</b><span>Bundles, gift bars, timers and trust blocks share your fonts and colours, so the store feels like one brand instead of eight widgets.</span></li>
                <li><b>One set of numbers</b><span>Instead of each app claiming the same sale, one analytics view credits each order line to the offer that added it.</span></li>
                <li><b>Less to maintain</b><span>One app to update, one support team to contact, and one switch to turn things off.</span></li>
            </ul>
        </div>
    </section>

    <div class="mn-shot" style="margin-top:clamp(48px,7vw,88px)">@include('site.visuals.bundles')</div>

    <section class="mn-section" style="margin-top:clamp(48px,7vw,88px)">
        <div class="mn-narrow">
            <h2>Why stores add OrderOrbit Space</h2>
            <div class="mn-prose">
                <p>Most growth apps bolt a widget onto your product page and hope for the best. The widget fights your theme's add-to-cart, the discount needs a code nobody remembers, and you never find out whether it made money.</p>
                <p>OrderOrbit Space takes a different approach. Every offer is placed through Shopify's Theme Editor or right above your add-to-cart button, uses your store's fonts and colours, and applies its price at checkout automatically. A built-in Shopify pixel then shows how much revenue each offer brought in.</p>
            </div>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>From install to your first sale in minutes</h2>
            <ol class="mn-steps">
                <li><div><b>Install from the Shopify App Store</b><span>One click. Billing runs through your Shopify invoice.</span></div></li>
                <li><div><b>Pick a feature and a template</b><span>Start from a ready-made layout — quantity breaks, a gift bar, a countdown — and preview it with your own product.</span></div></li>
                <li><div><b>Make it yours</b><span>Set offers, products and variants, then adjust colours, sizes and text. The preview updates as you type.</span></div></li>
                <li><div><b>Publish and measure</b><span>It appears on your store right away; savings apply at checkout and Analytics shows the revenue it brings in.</span></div></li>
            </ol>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Built the careful way</h2>
            <ul class="mn-list">
                <li><b>Works with your theme</b><span>No theme code edits. Offers sit in Theme Editor blocks or next to your add-to-cart, and come out in one click.</span></li>
                <li><b>Real prices at checkout</b><span>Bundle prices, gifts and free shipping are applied by Shopify at checkout — no codes to copy.</span></li>
                <li><b>Real inventory</b><span>Bundles check out as one line, but orders keep every product, so stock is deducted per item.</span></li>
                <li><b>Honest urgency</b><span>Countdowns only use real deadlines. Timers never reset per visitor.</span></li>
                <li><b>Measured results</b><span>Revenue is credited to the offer that added each order line, and every offer shows its own numbers.</span></li>
                <li><b>Respects consent</b><span>Analytics only count shoppers who allow it, and no personal data is collected.</span></li>
            </ul>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Simple pricing, billed by Shopify</h2>
            <p class="mn-intro">Start on any plan. Upgrade, downgrade or cancel whenever you like from your Shopify admin.</p>
            <div class="mn-facts">
                @foreach ($plans as $key => $plan)
                    <div><b>${{ number_format($plan['price'], 2) }}</b><span><strong style="color:var(--text)">{{ $plan['name'] }}</strong> · per month<br>{{ implode(' · ', array_slice($plan['features'], 0, 3)) }}</span></div>
                @endforeach
            </div>
            <p style="margin:22px 0 0"><a class="btn" href="{{ route('site.pricing') }}">Compare plans</a></p>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>Questions</h2>
            @include('site.partials.faq', ['faqs' => [
                ['What is OrderOrbit Space?', 'A Shopify app that helps you raise order value and conversion with bundles, progressive gifts (free gifts, free shipping and discounts), cart upsells, countdowns, sticky add-to-cart and trust blocks — with built-in analytics that show what each offer earns.'],
                ['Can it replace the apps I already use?', 'For most stores, yes. If you run separate apps for bundles, free gifts, a shipping bar, upsells, timers, a sticky add-to-cart or trust badges, you can recreate those offers in OrderOrbit Space, check them on your store, then uninstall the old apps.'],
                ['Will it slow down or break my theme?', 'No. Offers load only on the pages where they appear, and each one is a small script. There are no theme code edits, and you can remove any block in one click.'],
                ['Do bundles work with my inventory?', 'Yes. A bundle shows as one line in the cart at the bundle price, but your orders keep each product, so Shopify deducts stock from every item as usual.'],
                ['Do shoppers need a discount code?', 'No. Bundle prices, gifts, free shipping and upsell incentives are applied automatically at checkout.'],
                ['How do you measure revenue?', 'A Shopify web pixel records completed orders and credits each order line to the offer that added it. Only shoppers who allow analytics are counted.'],
                ['What does it cost?', 'Starter is $9.99, Growth $29.99 and Scale $59.99 per month, billed through your Shopify invoice.'],
            ]])
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Ready to raise your order value?</h2>
            <p>Install OrderOrbit Space and publish your first bundle in a few minutes.</p>
            <div class="ctas">
                <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
                <a class="btn lg" href="{{ route('site.pricing') }}">See pricing</a>
            </div>
        </div>
    </section>
</div>
@endsection
