@extends('layouts.site')

@section('title', $feature['seo_title'])
@section('description', $feature['seo_description'])

@php
    $soon = ($feature['status'] ?? 'live') === 'soon';
    $names = collect($templates)->pluck('name')->unique()->values();
@endphp

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <nav class="mn-crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">Home</a><span>/</span><a href="{{ route('site.features') }}">Features</a><span>/</span><span>{{ $feature['name'] }}</span></nav>
            <span class="mn-kicker">{{ $feature['name'] }}@if ($soon)<span class="soon">Coming soon</span>@endif</span>
            <h1>{{ $feature['h1'] }}</h1>
            <p class="mn-lead">{{ $feature['hero'] }}</p>
            <div class="ctas">
                @if ($soon)
                    <a class="btn primary lg" href="{{ route('site.contact') }}">Ask about early access</a>
                @else
                    <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
                @endif
                <a class="btn lg" href="{{ route('site.pricing') }}">See pricing</a>
            </div>
        </div>
    </section>

    <div class="mn-shot">@include('site.visuals.'.$feature['slug'])</div>

    @if (! empty($feature['overview']))
        <section class="mn-section" style="margin-top:clamp(48px,7vw,88px)">
            <div class="mn-narrow">
                <h2>Overview</h2>
                <div class="mn-prose">
                    @foreach ($feature['overview'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
                </div>
            </div>
        </section>
    @endif

    @if (! empty($feature['benefits']))
        <section class="mn-section">
            <div class="mn-wide">
                <h2>What it gives you</h2>
                <ul class="mn-list">
                    @foreach ($feature['benefits'] as [$title, $text])<li><b>{{ $title }}</b><span>{{ $text }}</span></li>@endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>How it works</h2>
            <ol class="mn-steps">
                @foreach ($feature['steps'] as $step)<li><div>{{ $step }}</div></li>@endforeach
            </ol>
        </div>
    </section>

    @if (! empty($feature['example']))
        <section class="mn-section">
            <div class="mn-narrow">
                <div class="mn-note">
                    <h3>Example: {{ rtrim($feature['example']['title'], '.') }}</h3>
                    <p>{{ $feature['example']['text'] }}</p>
                </div>
            </div>
        </section>
    @endif

    @if ($names->isNotEmpty() && ! $soon)
        <section class="mn-section">
            <div class="mn-narrow">
                <h2>Templates</h2>
                <p class="mn-intro">{{ $names->count() }} ready-made {{ \Illuminate\Support\Str::plural('layout', $names->count()) }}. Pick one in the app, then change the text, colours, sizes and spacing to match your store.</p>
                <ul class="mn-chips">@foreach ($names as $name)<li>{{ $name }}</li>@endforeach</ul>
                <a class="btn" href="{{ route('site.templates') }}">See the template gallery</a>
            </div>
        </section>
    @endif

    @if (! empty($feature['faqs']))
        <section class="mn-section">
            <div class="mn-narrow">
                <h2>Questions</h2>
                @include('site.partials.faq', ['faqs' => $feature['faqs']])
            </div>
        </section>
    @endif

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Works well with</h2>
            <ul class="mn-rows">
                @foreach ($related as $slug => $r)
                    <li><a href="{{ route('site.feature', $slug) }}"><b>{{ $r['name'] }}@if (($r['status'] ?? 'live') === 'soon')<span class="soon">Soon</span>@endif</b><span>{{ $r['summary'] ?? $r['menu'] }}</span><i>Learn more →</i></a></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>{{ $soon ? 'Want it first?' : 'Try '.$feature['name'].' on your store' }}</h2>
            <p>{{ $soon ? 'Tell us about your store and we\'ll let you know when it\'s ready.' : 'Install OrderOrbit Space, pick a template and publish in a few minutes.' }}</p>
            <div class="ctas">
                @if ($soon)
                    <a class="btn primary lg" href="{{ route('site.contact') }}">Contact us</a>
                @else
                    <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
