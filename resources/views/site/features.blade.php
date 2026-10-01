@extends('layouts.site')

@section('title', 'Features | OrderOrbit Space — Bundles, Gifts, Upsells for Shopify')
@section('description', 'Bundles, progressive gifts, cart upsells, countdowns, sticky add-to-cart, trust blocks and analytics for Shopify, in one app.')

@php
    $features = \App\Support\Content::features();
    $groups = ['convert' => ['Raise order value and conversion', 'On your product pages and in the cart.'], 'grow' => ['Measure and grow', 'See what each offer earns.'], 'checkout' => ['After the Buy button', 'Checkout, Thank You and customer accounts.']];
@endphp

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Features</span>
            <h1>Everything that raises order value, <em>in one app.</em></h1>
            <p class="mn-lead">Each feature has ready-made templates, shares your store's design and applies its savings at checkout. Features marked "coming soon" are being built now.</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-wide">
            @foreach ($groups as $group => [$title, $intro])
                @php($items = array_filter($features, fn ($f) => $f['group'] === $group))
                @php(uasort($items, fn ($a, $b) => (($a['status'] ?? 'live') === 'soon') <=> (($b['status'] ?? 'live') === 'soon')))
                <p class="mn-group">{{ $title }}</p>
                <p class="mn-intro" style="margin-bottom:16px">{{ $intro }}</p>
                <ul class="mn-rows" style="margin-bottom:48px">
                    @foreach ($items as $slug => $f)
                        <li><a href="{{ route('site.feature', $slug) }}"><b>{{ $f['name'] }}@if (($f['status'] ?? 'live') === 'soon')<span class="soon">Coming soon</span>@endif</b><span>{{ $f['summary'] ?? $f['menu'] }}</span><i>Learn more →</i></a></li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Start with one feature</h2>
            <p>Most stores begin with a bundle or a gift bar and add the rest later. Every plan, including Free, has every live feature.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a><a class="btn lg" href="{{ route('site.pricing') }}">See pricing</a></div>
        </div>
    </section>
</div>
@endsection
