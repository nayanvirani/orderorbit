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
    $features = \App\Support\Content::features();
    $live = array_filter($features, fn ($f) => ($f['status'] ?? 'live') === 'live');
    $soon = array_filter($features, fn ($f) => ($f['status'] ?? 'live') === 'soon');
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

    <div class="mn-shot">@include('site.visuals.bundles')</div>

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
        <div class="mn-wide">
            <h2>What you get</h2>
            <p class="mn-intro">Everything below is in the app today. Each feature has ready-made templates you can restyle to match your store.</p>
            <ul class="mn-rows">
                @foreach ($live as $slug => $f)
                    <li><a href="{{ route('site.feature', $slug) }}"><b>{{ $f['name'] }}</b><span>{{ $f['summary'] ?? $f['menu'] }}</span><i>Learn more →</i></a></li>
                @endforeach
            </ul>
            @if ($soon)
                <p class="mn-group" style="margin-top:36px">Coming soon</p>
                <p class="mn-intro" style="margin-bottom:0">{{ collect($soon)->pluck('name')->implode(' · ') }}</p>
            @endif
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
