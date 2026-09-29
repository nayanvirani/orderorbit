@extends('layouts.site')

@section('title', 'About | OrderOrbit')
@section('description', 'OrderOrbit is a Shopify-native growth platform: native placement, honest measurement, merchant control and privacy by default.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>About</span>
        <h1>Built to help Shopify brands <span class="grad-text">grow the right way.</span></h1>
        <p class="lead">OrderOrbit is a Shopify-native growth platform. We believe conversion tools should work with your theme, not against it; that results should be measured honestly; and that one well-built app beats a stack of scripts.</p>
        <div class="ctas">
            <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install on Shopify</a>
            <a class="btn lg" href="{{ route('site.contact') }}">Contact us</a>
        </div>
    </div>
</section>
<section class="section dark">
    <div class="wrap">
        <div class="section-head reveal"><span class="eyebrow"><span class="dot"></span>Our principles</span><h2>What we won't compromise on.</h2></div>
        <div class="grid four">
            @foreach ([['layout', 'Native placement', 'Every block is placed by you in the Theme Editor. No theme hacks.'], ['chart', 'Honest measurement', 'Clearly labelled attribution models, and winners only when the sample says so.'], ['user', 'Merchant control', 'You decide what shows, where, and to whom — and removing it is one click.'], ['shield', 'Privacy by default', 'Consent-aware analytics, data minimisation and deletion on request.']] as [$icon, $title, $text])
                <div class="card reveal"><div class="icon-badge"><x-icon :name="$icon"/></div><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
            @endforeach
        </div>
    </div>
</section>
<section class="section">
    <div class="wrap" style="max-width:640px">@include('site.diagrams.orbit', ['light' => true])</div>
</section>
@include('site.partials.cta')
@endsection
