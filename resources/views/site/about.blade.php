@extends('layouts.site')

@section('title', 'About | OrderOrbit Space')
@section('description', 'OrderOrbit Space helps Shopify stores raise order value with offers that respect the theme, measure honestly and stay in the merchant\'s control.')

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">About</span>
            <h1>Growth tools that <em>work with your store,</em> not against it.</h1>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-narrow mn-prose">
            <p>OrderOrbit Space started with a simple frustration: most Shopify growth apps feel bolted on. They fight the theme's add-to-cart, ask shoppers to type discount codes, and leave merchants guessing whether they made any money.</p>
            <p>We think offers should look like part of your store, apply their prices where Shopify applies prices — at checkout — and report honestly on what they earned. One well-built app, with one design and one set of numbers, beats a stack of add-ons.</p>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>What we won't compromise on</h2>
            <ul class="mn-list">
                <li><b>Native placement</b><span>Offers sit in Theme Editor blocks or next to your add-to-cart. No theme code edits, and removing one is a click.</span></li>
                <li><b>Honest measurement</b><span>Revenue is credited to the offer that added each order line, and nothing is claimed that we can't measure.</span></li>
                <li><b>Honest urgency</b><span>Countdowns only use real deadlines. We don't build fake scarcity.</span></li>
                <li><b>Your control</b><span>You decide what shows, where and to whom — and your team's roles decide who can publish.</span></li>
                <li><b>Privacy by default</b><span>Analytics respect shoppers' consent and never collect personal data.</span></li>
                <li><b>Clear about what's next</b><span>Features still in progress are marked "coming soon" until they're in the app.</span></li>
            </ul>
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Questions, ideas or partnerships?</h2>
            <p>We read every message and reply within one business day.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ route('site.contact') }}">Contact us</a><a class="btn lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a></div>
        </div>
    </section>
</div>
@endsection
