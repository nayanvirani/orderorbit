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
            <p class="mn-lead">Bundles, progressive gifts, upsells, countdowns, pre-orders, sales pops and trust blocks that look like part of your store, apply their savings at checkout, and show you exactly what they earn.</p>
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
            ['preorder', 'preorder', 'Product page', 'p8'],
            ['sales-pop', 'salespop', 'Every page', 'p9'],
        ];
        $soonFeatures = array_filter($features, fn ($f) => ($f['status'] ?? 'live') === 'soon');
    @endphp
    <section class="mn-orbit" aria-label="Every OrderOrbit Space feature">
        <div class="ring r1"></div>
        <div class="ring r2"></div>
        <div class="planet">
            <span class="planet-name">OrderOrbit Space</span>
            <span class="planet-sub">{{ count($features) - count($soonFeatures) }} features live{{ count($soonFeatures) ? ' · '.count($soonFeatures).' on the way' : '' }}</span>
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
                @elseif ($type === 'preorder')
                    <div class="mk-po" style="margin:0">
                        <div class="mk-po-head"><span class="mk-po-badge">Pre-order</span><span><x-icon name="calendar"/> Ships 22 Nov</span></div>
                        <div class="mk-po-tiles"><div><b>1</b><small>MONTH</small></div><div><b>3</b><small>WEEKS</small></div><div><b>2</b><small>DAYS</small></div></div>
                    </div>
                @elseif ($type === 'salespop')
                    <div class="mk-sp-toast" style="position:static;width:auto;box-shadow:none"><div class="ph art b"></div><div><b>Someone in Canada</b><span>purchased <strong>Glow Serum</strong></span><small>12 minutes ago</small></div></div>
                @else
                    @include('site.partials.thumb', ['type' => $type, 'v' => $type === 'trust' ? 0 : 1])
                @endif
            </a>
        @endforeach
    </section>

    @php
        $stack = [
            ['bundle', 'Bundles & quantity breaks', '$10–40'],
            ['gift', 'Free gift with purchase', '$10–30'],
            ['truck', 'Free shipping bar', '$5–15'],
            ['sparkle', 'Cart upsells', '$10–40'],
            ['clock', 'Countdown timer', '$5–15'],
            ['cursor', 'Sticky add to cart', '$5–15'],
            ['shield', 'Trust badges & reviews', '$5–20'],
            ['chart', 'Revenue analytics', '$20–50'],
            ['calendar', 'Pre-orders', '$10–30'],
            ['bell', 'Sales pop notifications', '$5–20'],
        ];
        $fromPrice = number_format(min(array_filter(array_column($plans, 'price'))), 2);
    @endphp
    <section class="hx-stack">
        <div class="mn-wide">
            <div class="hx-head">
                <span class="mn-kicker">Your app stack</span>
                <h2>Replace 5–10 apps <em>with one.</em></h2>
                <p>Most stores add a separate app for every job. Each one brings its own bill, its own script and its own look. OrderOrbit Space does all of these jobs in one place.</p>
            </div>
            <div class="hx-merge">
                <div class="hx-before">
                    <p class="hx-tag">Today · {{ count($stack) }} separate apps</p>
                    <ul>
                        @foreach ($stack as [$icon, $job, $cost])
                            <li style="--r:{{ [-2, 1.5, -1, 2, 1, -1.5, 2, -2, 1.5, -1][$loop->index % 10] }}deg"><x-icon :name="$icon"/><span>{{ $job }}</span><b>{{ $cost }}<small>/mo</small></b></li>
                        @endforeach
                    </ul>
                </div>
                <div class="hx-arrow" aria-hidden="true"><span></span><x-icon name="arrow"/></div>
                <div class="hx-after">
                    <p class="hx-tag">After · 1 app</p>
                    <div class="hx-card">
                        <div class="hx-brand"><svg aria-hidden="true"><use href="#i-orbit"></use></svg>OrderOrbit Space</div>
                        <ul>
                            @foreach ($stack as [$icon, $job])
                                <li><x-icon name="check"/>{{ $job }}</li>
                            @endforeach
                        </ul>
                        <div class="hx-price"><span>free, then from</span><b>${{ $fromPrice }}</b><span>/ month</span></div>
                    </div>
                </div>
            </div>
            <div class="hx-stats">
                <div><span>Apps to manage</span><b>{{ count($stack) }} <i>→</i> 1</b></div>
                <div><span>Typical monthly cost</span><b>$85–275 <i>→</i> $0–{{ number_format(max(array_column($plans, "price")), 2) }}</b></div>
                <div><span>Scripts on your pages</span><b>{{ count($stack) }} <i>→</i> 1</b></div>
            </div>
            <p class="hx-fine">Separate-app prices are typical monthly ranges for single-purpose apps on the Shopify App Store; your own bills may differ.</p>
        </div>
    </section>

    <section class="mn-section hx-after-sec">
        <div class="mn-wide">
            <div class="hx-head light">
                <span class="mn-kicker">After you install</span>
                <h2>What changes <em>on day one.</em></h2>
            </div>
            <div class="hx-bento">
                <article class="t-bill">
                    <div class="viz">
                        <div class="inv">
                            <p class="inv-h"><span>Shopify invoice</span><span>Apps</span></p>
                            <p class="inv-row"><span><svg aria-hidden="true"><use href="#i-orbit"></use></svg>OrderOrbit Space · {{ $plans['growth']['name'] }}</span><b>${{ number_format($plans['growth']['price'], 2) }}</b></p>
                            <p class="inv-gone"><span>Bundle app</span><span>Gift app</span><span>Timer app</span><span>Upsell app</span><span>+6 more</span></p>
                        </div>
                    </div>
                    <h3>One bill instead of ten</h3>
                    <p>One subscription on your Shopify invoice, and one place to manage every offer.</p>
                </article>
                <article class="t-speed">
                    <div class="viz">
                        <div class="bars">
                            <p>Separate apps</p>
                            <div class="b-stack">@foreach ([78, 64, 52, 70, 46, 58, 40, 66, 56, 72] as $w)<i style="width:{{ $w }}%"></i>@endforeach</div><p class="cap">10 scripts, 10 stylesheets</p>
                            <p style="margin-top:30px">OrderOrbit Space</p>
                            <div class="b-one"><i></i></div><p class="cap">1 shared runtime</p>
                        </div>
                    </div>
                    <h3>A lighter, faster store</h3>
                    <p>Every app adds its own scripts and styles. Here one small shared runtime loads, and each feature only on pages where it appears.</p>
                </article>
                <article class="t-conflict">
                    <div class="viz"><div class="atc"><span class="qty">− 1 +</span><span class="btn-atc">Add to cart</span></div><p class="ok"><x-icon name="check"/>One add-to-cart, no clashes</p></div>
                    <h3>No more app conflicts</h3>
                    <p>Features are built to work together and never edit theme code.</p>
                </article>
                <article class="t-look">
                    <div class="viz"><div class="sw"><i style="background:#0a0a0a"></i><i style="background:#d6c7b0"></i><i style="background:#f4efe8"></i><i style="background:#7a8b6f"></i></div><span class="aa">Aa</span></div>
                    <h3>One look everywhere</h3>
                    <p>Every block shares your fonts and colours, so it feels like one brand.</p>
                </article>
                <article class="t-numbers">
                    <div class="viz"><div class="mini-kpi"><div><span>Revenue from offers</span><b>$4,280</b><em>▲ 18%</em></div><div class="bars">@foreach ([38, 52, 44, 63, 58, 76, 88] as $h)<i style="height:{{ $h }}%"></i>@endforeach</div></div></div>
                    <h3>One set of numbers</h3>
                    <p>Each order line is credited to the offer that added it — no double counting.</p>
                </article>
                <article class="t-maint">
                    <div class="viz"><div class="tg"><span>All offers</span><i></i></div><div class="tg off"><span>Theme code edits</span><i></i></div></div>
                    <h3>Less to maintain</h3>
                    <p>One app to update, one support team, one switch to turn things off.</p>
                </article>
            </div>
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
        <div class="mn-wide hx-careful">
            <div class="hx-careful-side">
                <span class="mn-kicker">Our promises</span>
                <h2>Built the <em>careful</em> way.</h2>
                <p>Six rules every OrderOrbit Space feature follows, so growing order value never costs you trust, stock accuracy or a broken theme.</p>
                <a class="btn" href="{{ route('site.security') }}">Security &amp; privacy</a>
            </div>
            <ol class="hx-promises">
                @foreach ([
                    ['layout', 'Works with your theme', 'No theme code edits. Offers sit in Theme Editor blocks or next to your add-to-cart, and come out in one click.'],
                    ['card', 'Real prices at checkout', 'Bundle prices, gifts and free shipping are applied by Shopify at checkout — no codes to copy.'],
                    ['layers', 'Real inventory', 'Bundles check out as one line, but orders keep every product, so stock is deducted per item.'],
                    ['clock', 'Honest urgency', 'Countdowns only use real deadlines. Timers never reset per visitor.'],
                    ['chart', 'Measured results', 'Revenue is credited to the offer that added each order line, and every offer shows its own numbers.'],
                    ['lock', 'Respects consent', 'Analytics only count shoppers who allow it, and no personal data is collected.'],
                ] as [$icon, $title, $text])
                    <li>
                        <span class="n">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="ic"><x-icon :name="$icon"/></span>
                        <div><b>{{ $title }}</b><p>{{ $text }}</p></div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Start free, upgrade as you grow</h2>
            <p class="mn-intro">The Free plan covers a new store. Paid plans lift the limits and add more as your sales grow. Billed through Shopify.</p>
            @include('site.partials.plan-cards', ['plans' => $plans])
            <p style="margin:28px 0 0;text-align:center"><a class="btn" href="{{ route('site.pricing') }}">Compare all plan details</a></p>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>Questions</h2>
            @include('site.partials.faq', ['faqs' => [
                ['What is OrderOrbit Space?', 'A Shopify app that helps you raise order value and conversion with bundles, progressive gifts (free gifts, free shipping and discounts), cart upsells, countdowns, pre-orders, sales pop, sticky add-to-cart and trust blocks — with built-in analytics that show what each offer earns.'],
                ['Can it replace the apps I already use?', 'For most stores, yes. If you run separate apps for bundles, free gifts, a shipping bar, upsells, timers, pre-orders, sales pops, a sticky add-to-cart or trust badges, you can recreate those offers in OrderOrbit Space, check them on your store, then uninstall the old apps.'],
                ['Will it slow down or break my theme?', 'No. Offers load only on the pages where they appear, and each one is a small script. There are no theme code edits, and you can remove any block in one click.'],
                ['Do bundles work with my inventory?', 'Yes. A bundle shows as one line in the cart at the bundle price, but your orders keep each product, so Shopify deducts stock from every item as usual.'],
                ['Do shoppers need a discount code?', 'No. Bundle prices, gifts, free shipping and upsell incentives are applied automatically at checkout.'],
                ['How do you measure revenue?', 'A Shopify web pixel records completed orders and credits each order line to the offer that added it. Only shoppers who allow analytics are counted.'],
                ['What does it cost?', 'It\'s free while your store sells up to $1,000 a month, with the core widgets and one of each revenue feature. Paid plans are $14.99, $29.99 and $59.99 a month, billed through your Shopify invoice.'],
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
