@extends('layouts.site')

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'OrderOrbit',
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Shopify',
    'description' => 'Bundles, upsells, free gifts, shipping bars, checkout blocks, automation, analytics and A/B testing for Shopify — in one app.',
    'offers' => array_values(array_map(fn ($p) => ['@type' => 'Offer', 'name' => $p['name'], 'price' => $p['price'], 'priceCurrency' => 'USD'], $plans)),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@php
    $chapters = [
        'convert' => ['01', 'Convert', 'Every conversion tool, one <em>consistent</em> design.', 'Bundles, free gifts, shipping progress, quantity breaks, product and cart upsells, countdowns, sticky add-to-cart and trust badges — sharing your brand colours, fonts and analytics.', 'bundles'],
        'checkout' => ['02', 'Checkout', 'Keep selling <em>after</em> the Buy button.', 'Add reviews, trust, shipping progress and offers to checkout where Shopify supports it, and turn Thank You, Order Status and Customer Account pages into reorders, reviews and referrals.', 'checkout'],
        'grow' => ['03', 'Grow', 'Measure it. Test it. <em>Automate</em> it.', 'Consent-aware analytics, A/B tests with honest results, audience rules and lifecycle workflows — all reading from the same numbers.', 'automation'],
    ];
    $thumbFor = ['bundles' => 'bundle', 'free-gift' => 'gift', 'free-shipping-bar' => 'shipping', 'quantity-breaks' => 'qty', 'upsell-cross-sell' => 'upsell', 'countdown-timer' => 'countdown', 'sticky-add-to-cart' => 'sticky', 'trust-social-proof' => 'trust', 'customer-accounts' => 'account', 'checkout' => 'thankyou'];
@endphp

@section('content')
{{-- Hero: product screens in orbit --}}
<section class="orbit-hero sky">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Shopify CRO, checkout &amp; customer experience<span class="dot"></span></span>
        <h1>Convert more customers. <em>Increase order value.</em> Bring customers back.</h1>
        <p class="lead">OrderOrbit gives your Shopify store bundles, upsells, free-gift and shipping incentives, checkout and thank-you blocks, lifecycle automation, analytics and A/B testing — in one app, placed natively through the Theme Editor.</p>
        <div class="ctas">
            <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
            <a class="btn lg" href="{{ route('site.how') }}" data-event="cta_how_it_works_clicked">See How It Works</a>
        </div>
        <div class="assure"><span>No theme code edits</span><span>·</span><span>Billed through Shopify</span><span>·</span><span>Remove any block in one click</span></div>
    </div>
    <div class="orbit-stage" aria-label="OrderOrbit experiences on a Shopify store">
        <div class="ring r2"></div>
        <div class="ring r1"></div>
        <div class="planet"></div>
        @foreach ([['s1', 'shipping', 'Shipping bar', 'Cart'], ['s2', 'bundle', 'Bundle', 'Product page'], ['s3', 'countdown', 'Countdown', 'Any page'], ['s4', 'trust', 'Reviews', 'Product page'], ['s5', 'thankyou', 'Thank You', 'Post-purchase']] as [$pos, $type, $label, $where])
            <div class="sat {{ $pos }}">
                <div class="sat-label"><span>{{ $label }}</span><b>{{ $where }}</b></div>
                @include('site.partials.thumb', ['type' => $type, 'v' => $loop->index])
            </div>
        @endforeach
    </div>
</section>

<div class="marquee" aria-hidden="true">
    <div class="track">
        @foreach ([1, 2] as $copy)
            @foreach (['Bundles', 'Free gifts', 'Shipping bar', 'Quantity breaks', 'Upsells', 'Countdowns', 'Sticky add to cart', 'Trust & reviews', 'Checkout blocks', 'Thank You pages', 'Customer accounts', 'Automation', 'Analytics', 'A/B testing', 'Personalization'] as $word)
                <span>{{ $word }}</span>
            @endforeach
        @endforeach
    </div>
</div>

{{-- Problem --}}
<section class="section">
    <div class="wrap">
        <div class="problem reveal">
            <div>
                <span class="tag"><x-icon name="alert"/>The problem</span>
                <h2>Too many apps. Not enough answers.</h2>
                <p>Most stores run one app for bundles, another for upsells, another for timers and another for email — each with its own settings, styles and reports. Pages slow down, themes break, and nobody can say which change actually moved revenue.</p>
                <a class="link-arrow" style="margin-top:26px" href="{{ route('site.how') }}" data-event="cta_how_it_works_clicked">See How OrderOrbit Works <x-icon name="arrow"/></a>
            </div>
            <div class="stack-art" aria-hidden="true">
                @foreach ([['bundle', 'Bundle app'], ['sparkle', 'Upsell app'], ['clock', 'Timer app'], ['mail', 'Email app'], ['shield', 'Badges app'], ['chart', 'Analytics app']] as [$icon, $name])
                    <div class="app x"><x-icon :name="$icon"/>{{ $name }}<div class="line" style="width:80%"></div><div class="line" style="width:55%"></div></div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- The loop --}}
<section class="section invert sky">
    <div class="wrap split">
        <div class="reveal">@include('site.diagrams.orbit')</div>
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>The core loop</span>
            <h2>One loop <em>for growth.</em></h2>
            <p class="lead">Build an experience, place it in your theme, see what it earns, test a better version, show it to the right shoppers and follow up automatically.</p>
            <ol class="num-list" style="margin-top:28px">
                <li><strong>Create</strong><p>Start from a proven template.</p></li>
                <li><strong>Publish</strong><p>Place it natively through the Theme Editor.</p></li>
                <li><strong>Measure</strong><p>Consent-aware analytics on every view, click and order.</p></li>
                <li><strong>Test, personalize, automate</strong><p>From the same data, in the same app.</p></li>
            </ol>
        </div>
    </div>
</section>

{{-- How it works --}}
<section class="section" id="how-it-works">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>How it works</span>
            <h2>From install to your first test <em>in minutes.</em></h2>
        </div>
        <ol class="steps horizontal">
            @foreach ([
                ['Connect Shopify', 'Install and choose your goal: conversion, AOV, repeat purchase or checkout.'],
                ['Pick a template', 'Start from a proven bundle, upsell, gift or shipping design.'],
                ['Customise and place', 'Match your brand, then drop the block anywhere in the Theme Editor.'],
                ['Measure and improve', 'Track views, clicks and revenue, then A/B test your next idea.'],
            ] as [$title, $text])
                <li class="step reveal"><span class="num">{{ $loop->iteration }}</span><div><h3>{{ $title }}</h3><p>{{ $text }}</p></div></li>
            @endforeach
        </ol>
    </div>
</section>

{{-- Chapters --}}
@foreach ($chapters as $group => [$chNumber, $chLabel, $chHeading, $chBody, $chHero])
    <section class="section {{ $loop->iteration === 2 ? 'invert' : ($loop->odd ? 'alt' : '') }}" id="{{ $group }}">
        <div class="wrap chapter">
            <div class="chapter-side reveal">
                <div class="chapter-mark">{{ $chNumber }}</div>
                <span class="eyebrow"><span class="dot"></span>{{ $chLabel }}</span>
                <h2>{!! $chHeading !!}</h2>
                <p>{{ $chBody }}</p>
                <a class="link-arrow" href="{{ route('site.features') }}#{{ $group }}">All {{ strtolower($chLabel) }} features <x-icon name="arrow"/></a>
            </div>
            <div class="bento">
                @foreach ($groups[$group] as $slug => $f)
                    @php
                        // Hero tile spans the row; the rest fill rows of three (checkout: two), and a
                        // lone last tile stretches so no row is left with one card.
                        $big = $slug === $chHero;
                        $rest = count($groups[$group]) - 1;
                        $perRow = $rest === 2 ? 2 : 3;
                        $position = array_search($slug, array_keys(array_filter($groups[$group], fn ($k) => $k !== $chHero, ARRAY_FILTER_USE_KEY)), true);
                        $lonely = ! $big && $rest % $perRow === 1 && $position === $rest - 1;
                        $width = $big || $lonely ? 'w6' : ($perRow === 2 ? 'w3' : 'w2');
                        $screen = $big || $group === 'grow' ? 'visual' : (isset($thumbFor[$slug]) ? 'thumb' : null);
                    @endphp
                    <a class="b-tile go-arrow reveal {{ $width }}" href="{{ route('site.feature', $slug) }}">
                        <span class="tile-tag">{{ $f['eyebrow'] }}</span>
                        <h3>{{ $f['name'] }}</h3>
                        <p>{{ $big ? $f['hero'] : $f['menu'] }}</p>
                        @if ($screen === 'visual')
                            <div class="bento-screen">@include('site.visuals.'.$slug)</div>
                        @elseif ($screen === 'thumb')
                            <div class="bento-screen"><div>@include('site.partials.thumb', ['type' => $thumbFor[$slug], 'v' => $loop->index])</div></div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endforeach

{{-- Built the Shopify way --}}
<section class="section invert">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Built the Shopify way</span>
            <h2>Theme-safe <em>by design.</em></h2>
            <p class="lead">No theme code edits, no forced cart drawers, no duplicate add-to-cart logic. You place every block in the Theme Editor, and removing it is one click.</p>
        </div>
        <div class="stat-band reveal">
            <div><b>0</b><span>lines of theme code edited</span></div>
            <div><b>0</b><span>forced cart drawers or pop-ups</span></div>
            <div><b>1</b><span>click to remove any block</span></div>
            <div><b>1</b><span>app, one design, one set of numbers</span></div>
        </div>
    </div>
</section>

{{-- Templates --}}
<section class="section alt">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Templates</span>
            <h2>Start from <em>a template.</em></h2>
            <p class="lead">Browse bundle, gift, shipping, upsell, countdown, trust, checkout and automation templates.</p>
        </div>
        <div class="tpl-grid tpl-teaser">
            @foreach ($templateTeaser as $tpl)
                <a class="tpl reveal" href="{{ route('site.templates', ['type' => $tpl['type']]) }}" style="text-decoration:none">
                    <div class="thumb"><div>@include('site.partials.thumb', ['type' => $tpl['type'], 'v' => $loop->index])</div></div>
                    <div class="meta"><b>{{ $tpl['name'] }}</b><span><span class="pill">{{ $tpl['label'] }}</span></span></div>
                </a>
            @endforeach
        </div>
        <div class="ctas" style="margin-top:36px"><a class="btn lg" href="{{ route('site.templates') }}" data-event="template_previewed">Browse Templates</a></div>
    </div>
</section>

{{-- Use cases --}}
<section class="section">
    <div class="wrap chapter">
        <div class="chapter-side reveal">
            <span class="eyebrow"><span class="dot"></span>Use cases</span>
            <h2>Built for growing <em>Shopify brands.</em></h2>
        </div>
        <ol class="num-list">
            @foreach ($solutions as $slug => $s)
                <li class="reveal">
                    <a class="uc-row" href="{{ route('site.solution', $slug) }}">
                        <span class="uc-name">{{ $s['name'] }}</span>
                        <span class="link-arrow">Explore <x-icon name="arrow"/></span>
                    </a>
                    <p>{{ $s['h1'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- Pricing --}}
<section class="section alt">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Pricing</span>
            <h2>Plans that grow <em>with your store.</em></h2>
            <p class="lead">Starter $9.99/mo • Growth $29.99/mo • Scale $59.99/mo — billed through Shopify.</p>
        </div>
        @include('site.partials.plan-cards')
        <div class="ctas" style="margin-top:28px"><a class="link-arrow" href="{{ route('site.pricing') }}">Compare every plan <x-icon name="arrow"/></a></div>
    </div>
</section>

{{-- FAQ --}}
<section class="section">
    <div class="wrap faq-layout">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>FAQ</span>
            <h2>Frequently asked <em>questions.</em></h2>
        </div>
        @include('site.partials.faq', ['faqs' => [
            ['What is OrderOrbit?', 'OrderOrbit helps Shopify brands convert more visitors, raise order value and bring customers back with theme-safe CRO blocks, checkout experiences, lifecycle automation, analytics and A/B testing — in one app.'],
            ['Will OrderOrbit slow down or break my theme?', 'Storefront experiences are blocks that load only where you place them in the Theme Editor. There are no theme code edits, and removing a block is one click in the Theme Editor.'],
            ['Do I need to edit theme code?', 'No. You place and arrange every block in Shopify\'s Theme Editor.'],
            ['Does it work with my cart drawer?', 'Cart upsells appear in your cart drawer where your theme supports it, and fall back to the cart page where it doesn\'t. OrderOrbit never force-opens or intercepts your drawer.'],
            ['Can I customise checkout?', 'OrderOrbit adds blocks to checkout in the ways Shopify supports. Blocks inside the checkout steps require Shopify Plus; Thank You and Order Status blocks are available on all plans that support checkout blocks. We only show the targets your store supports.'],
            ['How is revenue attributed?', 'Revenue is attributed within a set window using clearly labelled first-touch, last-touch and experience-assisted models. Attribution is an analytical model, not proof of causality — use A/B tests to prove impact.'],
            ['How does A/B testing decide a winner?', 'At 95% confidence using standard statistical tests, and only after at least 7 days, 1,000 visitors and 100 conversions per variant.'],
            ['Is analytics consent-aware / GDPR-friendly?', 'Yes. Analytics respect your customers\' consent choices and your store\'s privacy settings in Shopify. You control retention, and we support data export and deletion.'],
            ['Can I send emails without setting up an email provider?', 'Yes. Sending is included with OrderOrbit. You only set a sender name and reply-to address.'],
            ['What does OrderOrbit cost, and is billing through Shopify?', 'Starter is $9.99/mo, Growth $29.99/mo and Scale $59.99/mo, all billed through your Shopify invoice.'],
        ]])
    </div>
</section>

@include('site.partials.cta', ['heading' => 'Convert more. Earn more per order. Win customers back.', 'secondary' => 'how'])
@endsection
