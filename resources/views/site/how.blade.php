@extends('layouts.site')

@section('title', 'How It Works | OrderOrbit Space')
@section('description', 'Install, pick a template, make it yours, publish and measure. How OrderOrbit Space works on your Shopify store.')

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">How it works</span>
            <h1>Five steps from install <em>to measured results.</em></h1>
            <p class="mn-lead">OrderOrbit Space follows one simple loop: create an offer, publish it, and see what it earns. No theme code, no discount codes, no guesswork.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a></div>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <ol class="mn-steps">
                <li><div><b>Install from the Shopify App Store</b><span>Approve the permissions and choose a plan. Billing runs through your Shopify invoice, so there's no separate card to add.</span></div></li>
                <li><div><b>Pick a feature and a template</b><span>Choose what you want to do — a quantity-break bundle, a gift bar, a countdown — then pick a ready-made layout. You can preview any template with one of your own products.</span></div></li>
                <li><div><b>Make it yours</b><span>Set offers, products and variants, then adjust colours, sizes and text in the editor. The live preview updates as you type, on desktop and mobile.</span></div></li>
                <li><div><b>Publish</b><span>Bundles and gift bars appear above or below your add-to-cart button automatically; other blocks go wherever you place them in the Theme Editor. Savings apply at checkout without codes.</span></div></li>
                <li><div><b>Measure</b><span>A Shopify pixel records views, adds to cart and orders, and credits each order line to the offer that added it. Keep what works and change what doesn't.</span></div></li>
            </ol>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>What happens on your store</h2>
            <div class="mn-prose">
                <p>Each offer is a small script that loads only on pages where it appears, so the rest of your store stays as fast as it is today. Offers use your theme's fonts and your chosen colours, and they never edit theme code.</p>
                <p>Where a bundle shows on a product page, it replaces your theme's variant picker and add-to-cart so shoppers don't add the product twice. In the cart the bundle is one line at the bundle price, and your order still lists every product so stock is deducted item by item.</p>
                <p>When you pause or delete an offer, it disappears from your store and its checkout pricing stops at the same moment.</p>
            </div>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>What's coming next</h2>
            <div class="mn-prose">
                <p>Checkout and Thank You blocks and lifecycle automation are live. We're building A/B testing, personalization and customer account blocks next. They'll appear in the app as they're ready, and the features pages mark them as coming soon until then.</p>
            </div>
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>See it on your own store</h2>
            <p>It takes a few minutes to publish your first offer.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a><a class="btn lg" href="{{ route('site.features') }}">Browse features</a></div>
        </div>
    </section>
</div>
@endsection
