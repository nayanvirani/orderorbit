@extends('layouts.site')

@section('title', 'Features | OrderOrbit — Shopify CRO, Checkout & Growth')
@section('description', 'Bundles, free gifts, shipping bars, upsells, checkout blocks, automation, analytics, A/B testing and personalization for Shopify.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Features</span>
        <h1>Every growth tool your store needs, <span class="grad-text">in one app.</span></h1>
        <p class="lead">One design system, one analytics layer, one bill.</p>
        <div class="ctas">
            <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install on Shopify</a>
            <a class="btn lg" href="{{ route('site.pricing') }}">See Pricing</a>
        </div>
    </div>
</section>

@foreach (['convert' => ['Convert', 'Turn more visitors into buyers and raise order value on the product page and cart.'], 'checkout' => ['Checkout', 'Keep selling at checkout, on Thank You and Order Status pages, and in customer accounts.'], 'grow' => ['Grow', 'Measure, test, personalize and automate from one set of numbers.']] as $group => [$label, $intro])
    <section class="section {{ $loop->even ? 'tint' : '' }} tight">
        <div class="wrap">
            <div class="section-head left reveal" style="margin-bottom:32px">
                <span class="eyebrow"><span class="dot"></span>{{ $label }}</span>
                <h2>{{ $intro }}</h2>
            </div>
            <div class="grid {{ count($groups[$group]) > 4 ? 'four' : 'three' }}">
                @foreach ($groups[$group] as $slug => $f)
                    <a class="card reveal" href="{{ route('site.feature', $slug) }}">
                        <div class="icon-badge"><x-icon :name="$f['icon']"/></div>
                        <h3>{{ $f['name'] }}</h3>
                        <p>{{ $f['hero'] }}</p>
                        <span class="more">Learn more <x-icon name="arrow"/></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endforeach

<section class="section dark tight">
    <div class="wrap">
        <div class="section-head left reveal" style="margin-bottom:32px">
            <span class="eyebrow"><span class="dot"></span>Platform</span>
            <h2>The foundation under every experience.</h2>
        </div>
        <div class="grid four">
            @foreach ([['grid', 'Template library', 'Proven presets for every surface.', route('site.templates')], ['palette', 'Brand settings', 'Colours, fonts, buttons and spacing shared by every block.', null], ['shield', 'Consent-aware analytics', 'Shopify Web Pixel and Customer Privacy API.', route('site.security')], ['card', 'Shopify Billing', 'Starter, Growth and Scale on your Shopify invoice.', route('site.pricing')]] as [$icon, $title, $text, $href])
                @if ($href)
                    <a class="card reveal" href="{{ $href }}"><div class="icon-badge"><x-icon :name="$icon"/></div><h3>{{ $title }}</h3><p>{{ $text }}</p></a>
                @else
                    <div class="card reveal"><div class="icon-badge"><x-icon :name="$icon"/></div><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
                @endif
            @endforeach
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
