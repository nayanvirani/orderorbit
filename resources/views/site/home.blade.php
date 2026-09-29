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

@section('content')
{{-- Hero --}}
<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><span class="dot"></span>Shopify CRO, Checkout &amp; Customer Experience</span>
            <h1>Convert more customers. <span class="grad-text">Increase order value.</span> Bring customers back.</h1>
            <p class="lead">OrderOrbit gives your Shopify store bundles, upsells, free-gift and shipping incentives, checkout and thank-you blocks, lifecycle automation, analytics and A/B testing — in one app, placed natively through the Theme Editor.</p>
            <div class="ctas">
                <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install on Shopify</a>
                <a class="btn lg" href="{{ route('site.how') }}" data-event="cta_how_it_works_clicked">See How It Works <x-icon name="arrow"/></a>
            </div>
            <div class="hero-note">
                <span><x-icon name="check-circle"/>No theme code edits</span>
                <span><x-icon name="check-circle"/>Billed through Shopify</span>
                <span><x-icon name="check-circle"/>Remove any block in one click</span>
            </div>
        </div>
        @include('site.visuals.hero')
    </div>
</section>

{{-- Trust strip --}}
<div class="strip">
    <div class="wrap">
        <span><x-icon name="bag"/>Built for Shopify</span>
        <span><x-icon name="layout"/>Placed in the Theme Editor</span>
        <span><x-icon name="card"/>Checkout blocks</span>
        <span><x-icon name="shield"/>Consent-aware analytics</span>
        <span><x-icon name="zap"/>Shopify Billing</span>
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
                <a class="btn" style="margin-top:24px" href="{{ route('site.how') }}" data-event="cta_how_it_works_clicked">See How OrderOrbit Works <x-icon name="arrow"/></a>
            </div>
            <div class="stack-art" aria-hidden="true">
                @foreach ([['bundle', 'Bundle app'], ['sparkle', 'Upsell app'], ['clock', 'Timer app'], ['mail', 'Email app'], ['shield', 'Badges app'], ['chart', 'Analytics app']] as [$icon, $name])
                    <div class="app x"><x-icon :name="$icon"/>{{ $name }}<div class="line w80"></div><div class="line w60"></div></div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Core loop --}}
<section class="section dark">
    <div class="wrap split">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>The core loop</span>
            <h2>One loop for growth.</h2>
            <p class="lead">Build an experience, place it in your theme, see what it earns, test a better version, show it to the right shoppers and follow up automatically.</p>
            <ul class="bullet-list">
                <li><x-icon name="check-circle"/><span><b style="color:#fff">Create</b> from a proven template</span></li>
                <li><x-icon name="check-circle"/><span><b style="color:#fff">Publish</b> natively through the Theme Editor</span></li>
                <li><x-icon name="check-circle"/><span><b style="color:#fff">Measure</b> with consent-aware analytics</span></li>
                <li><x-icon name="check-circle"/><span><b style="color:#fff">Test</b>, <b style="color:#fff">personalize</b> and <b style="color:#fff">automate</b> from the same data</span></li>
            </ul>
        </div>
        <div class="reveal">@include('site.diagrams.orbit')</div>
    </div>
</section>

{{-- How it works --}}
<section class="section" id="how-it-works">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>How it works</span>
            <h2>From install to your first test in minutes.</h2>
        </div>
        <div class="steps">
            @foreach ([
                ['Connect Shopify', 'Install and choose your goal: conversion, AOV, repeat purchase or checkout.'],
                ['Pick a template', 'Start from a proven bundle, upsell, gift or shipping design.'],
                ['Customise and place', 'Match your brand, then drop the block anywhere in the Theme Editor.'],
                ['Measure and improve', 'Track views, clicks and revenue, then A/B test your next idea.'],
            ] as [$title, $text])
                <div class="step reveal"><span class="num">{{ $loop->iteration }}</span><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
            @endforeach
        </div>
        <div class="ctas center" style="margin-top:40px"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install on Shopify</a></div>
    </div>
</section>

{{-- CRO suite showcase --}}
<section class="section tint">
    <div class="wrap" data-tabs>
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>CRO suite</span>
            <h2>Every conversion tool, one consistent design.</h2>
            <p class="lead">Bundles, free gifts, shipping progress, quantity breaks, product and cart upsells, countdowns, sticky add-to-cart and trust badges — sharing your brand colours, fonts and analytics.</p>
        </div>
        <div class="tabs" role="tablist">
            @foreach ($groups['convert'] as $slug => $f)
                <button class="tab" role="tab" id="tab-{{ $slug }}" aria-controls="panel-{{ $slug }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"><x-icon :name="$f['icon']"/>{{ $f['name'] }}</button>
            @endforeach
        </div>
        @foreach ($groups['convert'] as $slug => $f)
            <div class="tab-panel split" role="tabpanel" id="panel-{{ $slug }}" aria-labelledby="tab-{{ $slug }}" @unless ($loop->first) hidden @endunless>
                <div class="split-copy">
                    <span class="eyebrow"><span class="dot"></span>{{ $f['eyebrow'] }}</span>
                    <h3 style="font-size:clamp(24px,2.6vw,32px)">{{ $f['h1'] }}</h3>
                    <p class="lead">{{ $f['hero'] }}</p>
                    <ul class="bullet-list">
                        @foreach (array_slice($f['grid'], 0, 4) as $point)<li><x-icon name="check-circle"/>{{ $point }}</li>@endforeach
                    </ul>
                    <a class="btn dark" href="{{ route('site.feature', $slug) }}">Explore {{ $f['name'] }} <x-icon name="arrow"/></a>
                </div>
                @include('site.visuals.'.$slug)
            </div>
        @endforeach
    </div>
</section>

{{-- Checkout --}}
<section class="section">
    <div class="wrap split">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>Checkout &amp; post-purchase</span>
            <h2>Keep selling after the Buy button.</h2>
            <p class="lead">Add reviews, trust, shipping progress and offers to checkout where Shopify supports it, and turn Thank You, Order Status and Customer Account pages into reorders, reviews and referrals.</p>
            <a class="btn dark" href="{{ route('site.feature', 'checkout') }}">Explore Checkout <x-icon name="arrow"/></a>
        </div>
        <div class="reveal">@include('site.visuals.checkout')</div>
    </div>
</section>

{{-- Automation --}}
<section class="section tint">
    <div class="wrap split flip">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>Automation</span>
            <h2>Bring customers back automatically.</h2>
            <p class="lead">Review requests, welcome series, VIP rewards, reorder reminders and win-back offers — built on a visual trigger, condition and action canvas.</p>
            <div style="margin-top:24px">@include('site.diagrams.flow', ['steps' => ['Order delivered', 'Wait 5 days', 'Send review request']])</div>
            <a class="btn dark" href="{{ route('site.feature', 'automation') }}">Explore Automation <x-icon name="arrow"/></a>
        </div>
        <div class="reveal">@include('site.visuals.automation')</div>
    </div>
</section>

{{-- Analytics --}}
<section class="section">
    <div class="wrap split">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>Analytics</span>
            <h2>Know what's working.</h2>
            <p class="lead">Every view, click, add-to-cart and purchase is tracked with consent-aware analytics. See funnels, revenue by experience and the full customer journey.</p>
            <a class="btn dark" href="{{ route('site.feature', 'analytics') }}">Explore Analytics <x-icon name="arrow"/></a>
        </div>
        <div class="reveal">@include('site.visuals.analytics')</div>
    </div>
</section>

{{-- A/B testing --}}
<section class="section tint">
    <div class="wrap split flip">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>A/B testing</span>
            <h2>Test ideas, not guesses.</h2>
            <p class="lead">Run A/B or A/B/C tests on any experience with traffic allocation, primary and guardrail metrics, and clear results. We only call a winner when your sample criteria are met.</p>
            <a class="btn dark" href="{{ route('site.feature', 'ab-testing') }}">Explore A/B Testing <x-icon name="arrow"/></a>
        </div>
        <div class="reveal">@include('site.visuals.ab-testing')</div>
    </div>
</section>

{{-- Personalization --}}
<section class="section">
    <div class="wrap split">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>Personalization</span>
            <h2>The right offer for the right shopper.</h2>
            <p class="lead">Show a premium upsell to returning customers, a mobile bundle to mobile shoppers and a VIP offer to repeat buyers — with simple audience rules.</p>
            <a class="btn dark" href="{{ route('site.feature', 'personalization') }}">Explore Personalization <x-icon name="arrow"/></a>
        </div>
        <div class="reveal">@include('site.visuals.personalization')</div>
    </div>
</section>

{{-- Built the Shopify way --}}
<section class="section dark">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Built the Shopify way</span>
            <h2>Theme-safe by design.</h2>
            <p class="lead">No theme code edits, no forced cart drawers, no duplicate add-to-cart logic. You place every block in the Theme Editor, and removing it is one click.</p>
        </div>
        <div class="grid four">
            @foreach ([
                ['code-off', 'No theme code edits', 'Every storefront experience is a block you place in the Theme Editor.'],
                ['bag', 'No forced cart drawers', 'We never open or intercept your theme\'s drawer.'],
                ['cursor', 'No duplicate add-to-cart', 'Your theme keeps handling variants, quantity and cart.'],
                ['trash', 'One-click removal', 'Remove a block in the Theme Editor and it\'s gone.'],
            ] as [$icon, $title, $text])
                <div class="card reveal"><div class="icon-badge"><x-icon :name="$icon"/></div><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
            @endforeach
        </div>
    </div>
</section>

{{-- Templates teaser --}}
<section class="section">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Templates</span>
            <h2>Start from a template.</h2>
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
        <div class="ctas center" style="margin-top:36px"><a class="btn dark lg" href="{{ route('site.templates') }}" data-event="template_previewed">Browse Templates <x-icon name="arrow"/></a></div>
    </div>
</section>

{{-- Use cases --}}
<section class="section tint">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Use cases</span>
            <h2>Built for growing Shopify brands.</h2>
        </div>
        <div class="grid four">
            @foreach ($solutions as $slug => $s)
                <a class="card reveal" href="{{ route('site.solution', $slug) }}">
                    <div class="icon-badge"><x-icon :name="$s['icon']"/></div>
                    <h3>{{ $s['name'] }}</h3>
                    <p>{{ $s['h1'] }}</p>
                    <span class="more">Explore <x-icon name="arrow"/></span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Pricing --}}
<section class="section">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Pricing</span>
            <h2>Plans that grow with your store.</h2>
            <p class="lead">Starter $9.99/mo • Growth $29.99/mo • Scale $59.99/mo — billed through Shopify.</p>
        </div>
        @include('site.partials.plan-cards')
        <div class="ctas center" style="margin-top:32px"><a class="btn lg" href="{{ route('site.pricing') }}">See Pricing <x-icon name="arrow"/></a></div>
    </div>
</section>

{{-- FAQ --}}
<section class="section tint">
    <div class="wrap">
        <div class="section-head reveal"><h2>Frequently asked questions.</h2></div>
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
