@extends('layouts.site')

@section('title', 'Templates | OrderOrbit Space')
@section('description', 'Ready-made layouts for bundles, progressive gifts, cart upsells, countdowns, sticky add-to-cart and trust blocks. Pick one and make it yours.')

@php
    $features = \App\Support\Content::features();
    // One main template per live feature; the rest are listed by name.
    $byFeature = collect($templates)->groupBy('feature')->filter(fn ($t, $slug) => isset($features[$slug]) && ($features[$slug]['status'] ?? 'live') === 'live');
@endphp

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Templates</span>
            <h1>Start from a template, <em>finish with your brand.</em></h1>
            <p class="mn-lead">A template is a ready-made layout, not a fixed design. Pick one in the app, then change every offer, product, text, colour, size and spacing to match your store, with a live preview.</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-wide mn-tpl">
            @foreach ($byFeature as $slug => $list)
                @php($names = $list->pluck('name')->unique()->values())
                <article class="mn-tpl-item">
                    <div>
                        <span class="mn-kicker">{{ $names->count() }} {{ \Illuminate\Support\Str::plural('layout', $names->count()) }}</span>
                        <h3>{{ $features[$slug]['name'] }}</h3>
                        <p>{{ $features[$slug]['summary'] ?? $features[$slug]['menu'] }}</p>
                        <ul class="mn-chips">@foreach ($names as $name)<li>{{ $name }}</li>@endforeach</ul>
                        <a class="btn sm" href="{{ route('site.feature', $slug) }}">About {{ strtolower($features[$slug]['name']) }}</a>
                    </div>
                    <div>@include('site.visuals.'.$slug)</div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>How templates work</h2>
            <ol class="mn-steps">
                <li><div><b>Pick a feature</b><span>Bundles come in six types; progressive gifts combine free gifts, free shipping and discounts in one bar.</span></div></li>
                <li><div><b>Choose a template</b><span>Each feature has several layouts. Preview them with one of your own products before you start.</span></div></li>
                <li><div><b>Make it yours</b><span>Your theme's fonts are used by default. Colours, sizes, borders, text and custom CSS are all editable.</span></div></li>
            </ol>
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Try the templates on your store</h2>
            <p>Every template is available on every plan.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a></div>
        </div>
    </section>
</div>
@endsection
