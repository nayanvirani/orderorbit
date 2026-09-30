@extends('layouts.site')

@section('title', 'How It Works | OrderOrbit Space')
@section('description', 'From install to your first A/B test: create, publish, measure, test, personalize and automate with OrderOrbit Space.')

@section('content')
<section class="page-hero sky">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>How it works</span>
        <h1>From install to <span class="grad-text">your first test.</span></h1>
        <p class="lead">OrderOrbit Space runs one loop: create, publish, measure, test, personalize, automate.</p>
        <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install on Shopify</a></div>
    </div>
</section>

<section class="section dark tight">
    <div class="wrap" style="max-width:640px">@include('site.diagrams.orbit')</div>
</section>

@php
    $steps = [
        ['Install from the Shopify App Store.', 'Approve permissions; billing runs through Shopify.', 'install'],
        ['Choose your goal.', 'Conversion, order value, repeat purchase or checkout — we recommend where to start.', 'goal'],
        ['Pick a template.', 'Bundle and progressive gift layouts, ready-made and fully customisable.', 'bundles'],
        ['Customise and place.', 'Match your brand, then add the block in the Theme Editor. No code.', 'theme-editor'],
        ['Measure.', 'OrderOrbit Space tracks views, clicks, add-to-carts and purchases per experience.', 'analytics'],
        ['Test.', 'Run an A/B test and keep the version that wins.', 'ab-testing'],
        ['Personalize.', 'Show different experiences to different shoppers.', 'personalization'],
        ['Automate.', 'Follow up after purchase with reviews, reorders and win-back.', 'automation'],
    ];
@endphp
@foreach ($steps as [$title, $text, $visual])
    <section class="section {{ $loop->even ? 'tint' : '' }}">
        <div class="wrap split {{ $loop->even ? 'flip' : '' }}">
            <div class="split-copy reveal">
                <div class="step-mark"><span class="chapter-mark">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span class="eyebrow"><span class="dot"></span>Step {{ $loop->iteration }} of 8</span></div>
                <h2>{{ $title }}</h2>
                <p class="lead">{{ $text }}</p>
            </div>
            <div class="reveal">@include('site.visuals.'.$visual)</div>
        </div>
    </section>
@endforeach

<section class="section dark">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Built the Shopify way</span>
            <h2>No theme edits. No forced cart drawers. No duplicate add-to-cart. Remove any block in one click.</h2>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
