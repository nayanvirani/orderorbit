@extends('layouts.site')

@section('content')
<section class="hero">
    <div class="wrap">
        <div class="eyebrow">SHOPIFY CRO, CHECKOUT &amp; CUSTOMER EXPERIENCE</div>
        <h1>Convert more customers. Increase order value. Bring customers back.</h1>
        <p class="lead">OrderOrbit gives your Shopify store bundles, upsells, free-gift and shipping incentives, checkout and thank-you blocks, lifecycle automation, analytics and A/B testing — in one app, placed natively through the Theme Editor.</p>
        <div class="ctas">
            <a class="btn primary" href="{{ config('shopify.install_url') }}">Install on Shopify</a>
            <a class="btn" href="#how-it-works">See How It Works</a>
        </div>
    </div>
</section>

<div class="wrap strip">
    <span>Built for Shopify</span><span>Theme App Blocks</span><span>Checkout Extensions</span><span>Consent-aware Web Pixel</span><span>Shopify Billing</span>
</div>

<section class="block">
    <div class="wrap" style="text-align:center">
        <h2>Too many apps. Not enough answers.</h2>
        <p class="lead">Most stores run one app for bundles, another for upsells, another for timers and another for email — each with its own scripts, styles and reports. Pages slow down, themes break, and nobody can say which change actually moved revenue.</p>
        <h2 style="margin-top:40px">One loop for growth.</h2>
        <div class="loop"><span>Create</span><span>Publish</span><span>Measure</span><span>Test</span><span>Personalize</span><span>Automate</span></div>
        <p class="muted">Build an experience, place it in your theme, see what it earns, test a better version, show it to the right shoppers and follow up automatically.</p>
    </div>
</section>

<section class="block alt" id="how-it-works">
    <div class="wrap">
        <h2>From install to your first test in minutes.</h2>
        <div class="grid">
            <div class="card"><h3>1. Connect Shopify</h3><p>Install and choose your goal: conversion, AOV, repeat purchase or checkout.</p></div>
            <div class="card"><h3>2. Pick a template</h3><p>Start from a proven bundle, upsell, gift or shipping design.</p></div>
            <div class="card"><h3>3. Customise and place</h3><p>Match your brand, then drop the block anywhere in the Theme Editor.</p></div>
            <div class="card"><h3>4. Measure and improve</h3><p>Track views, clicks and revenue, then A/B test your next idea.</p></div>
        </div>
    </div>
</section>

<section class="block" id="features">
    <div class="wrap">
        <div class="grid">
            <div class="card"><h3>Every conversion tool, one consistent design.</h3><p>Bundles, free gifts, shipping progress, quantity breaks, product and cart upsells, countdowns, sticky add-to-cart and trust badges — sharing your brand colours, fonts and analytics.</p></div>
            <div class="card"><h3>Keep selling after the Buy button.</h3><p>Add reviews, trust, shipping progress and offers to checkout where Shopify supports it, and turn Thank You, Order Status and Customer Account pages into reorders, reviews and referrals.</p></div>
            <div class="card"><h3>Bring customers back automatically.</h3><p>Review requests, welcome series, VIP rewards, reorder reminders and win-back offers — built on a visual trigger, condition and action canvas.</p></div>
            <div class="card"><h3>Know what's working.</h3><p>Every view, click, add-to-cart and purchase is tracked through Shopify's consent-aware Web Pixel. See funnels, revenue by experience and the full customer journey.</p></div>
            <div class="card"><h3>Test ideas, not guesses.</h3><p>Run A/B or A/B/C tests with traffic allocation, primary and guardrail metrics. We only call a winner when your sample criteria are met.</p></div>
            <div class="card"><h3>The right offer for the right shopper.</h3><p>Show a premium upsell to returning customers, a mobile bundle to mobile shoppers and a VIP offer to repeat buyers — with simple audience rules.</p></div>
        </div>
    </div>
</section>

<section class="block alt">
    <div class="wrap" style="text-align:center">
        <h2>Theme-safe by design.</h2>
        <p class="lead">No theme code edits, no forced cart drawers, no duplicate add-to-cart logic. You place every block in the Theme Editor, and removing it is one click.</p>
    </div>
</section>

<section class="block">
    <div class="wrap">
        <h2 style="text-align:center">Plans that grow with your store.</h2>
        @include('site.partials.plans')
    </div>
</section>

<section class="block alt">
    <div class="wrap" style="max-width:820px">
        <h2>Frequently asked questions.</h2>
        <details><summary>Will OrderOrbit slow down or break my theme?</summary><p class="muted">Blocks are placed through Shopify's Theme App Extensions and load only where you add them. Removing a block is one click in the Theme Editor.</p></details>
        <details><summary>Do I need to edit theme code?</summary><p class="muted">No. Every storefront experience is a Theme App Block you place in the Theme Editor.</p></details>
        <details><summary>Can I customise checkout?</summary><p class="muted">Checkout blocks are available on the targets Shopify makes available to your plan. OrderOrbit detects what your store supports and only shows those options. Thank You and Order Status blocks are available more broadly.</p></details>
        <details><summary>How does A/B testing decide a winner?</summary><p class="muted">A variant is only called a winner at 95% confidence after at least 7 days, 1,000 visitors and 100 conversions per variant.</p></details>
        <details><summary>Is analytics consent-aware?</summary><p class="muted">Yes. Events are collected through Shopify's Web Pixel and respect the customer privacy settings of your store.</p></details>
        <details><summary>What does OrderOrbit cost, and is billing through Shopify?</summary><p class="muted">Starter is $9.99/mo, Growth $29.99/mo and Scale $59.99/mo, all billed through Shopify.</p></details>
    </div>
</section>

<section class="block" style="text-align:center">
    <div class="wrap">
        <h2>Convert more. Earn more per order. Win customers back.</h2>
        <div class="ctas"><a class="btn primary" href="{{ config('shopify.install_url') }}">Install on Shopify</a></div>
    </div>
</section>
@endsection
