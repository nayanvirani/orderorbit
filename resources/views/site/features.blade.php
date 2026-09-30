@extends('layouts.site')

@section('title', 'Features | OrderOrbit — Shopify CRO, Checkout & Growth')
@section('description', 'Bundles, progressive gifts, cart upsells, checkout blocks, automation, analytics, A/B testing and personalization for Shopify.')

@section('content')
<section class="page-hero sky">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Features</span>
        <h1>Every growth tool your store needs, <em>in one app.</em></h1>
        <p class="lead">One design system, one analytics layer, one bill.</p>
        <div class="ctas">
            <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
            <a class="btn lg" href="{{ route('site.pricing') }}">See Pricing</a>
        </div>
    </div>
</section>

@foreach (['convert' => ['01', 'Convert', 'Turn more visitors into buyers and raise order value on the product page and cart.'], 'checkout' => ['02', 'Checkout', 'Keep selling at checkout, on Thank You and Order Status pages, and in customer accounts.'], 'grow' => ['03', 'Grow', 'Measure, test, personalize and automate from one set of numbers.']] as $group => [$number, $label, $intro])
    <section class="section {{ $loop->odd ? 'alt' : '' }}" id="{{ $group }}">
        <div class="wrap chapter">
            <div class="chapter-side reveal">
                <div class="chapter-mark">{{ $number }}</div>
                <span class="eyebrow"><span class="dot"></span>{{ $label }}</span>
                <h2>{{ $intro }}</h2>
            </div>
            <div class="bento">
                @foreach ($groups[$group] as $slug => $f)
                    <a class="b-tile go-arrow {{ count($groups[$group]) > 4 ? 'w3' : 'w3' }} reveal" href="{{ route('site.feature', $slug) }}">
                        <span class="tile-tag">{{ $f['eyebrow'] }}</span>
                        <h3>{{ $f['name'] }}</h3>
                        <p>{{ $f['hero'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endforeach

<section class="section">
    <div class="wrap chapter">
        <div class="chapter-side reveal">
            <div class="chapter-mark">04</div>
            <span class="eyebrow"><span class="dot"></span>Platform</span>
            <h2>The foundation under <em>every experience.</em></h2>
        </div>
        <ol class="num-list cols">
            <li><strong>Template library</strong><p>Proven presets for every surface. <a class="link-arrow" href="{{ route('site.templates') }}">Browse</a></p></li>
            <li><strong>Brand settings</strong><p>Colours, fonts, buttons and spacing shared by every block.</p></li>
            <li><strong>Consent-aware analytics</strong><p>Respects your customers' consent choices. <a class="link-arrow" href="{{ route('site.security') }}">Security</a></p></li>
            <li><strong>Shopify Billing</strong><p>Starter, Growth and Scale on your Shopify invoice. <a class="link-arrow" href="{{ route('site.pricing') }}">Pricing</a></p></li>
        </ol>
    </div>
</section>

@include('site.partials.cta')
@endsection
