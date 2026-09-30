@extends('layouts.site')

@section('title', $solution['name'].' | OrderOrbit Space')
@section('description', $solution['seo_description'])

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <nav class="mn-crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">Home</a><span>/</span><a href="{{ route('site.solutions') }}">Solutions</a><span>/</span><span>{{ $solution['name'] }}</span></nav>
            <span class="mn-kicker">For {{ strtolower($solution['name']) }}</span>
            <h1>{{ $solution['h1'] }}</h1>
            <p class="mn-lead">{{ $solution['hero'] }}</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a><a class="btn lg" href="{{ route('site.pricing') }}">See pricing</a></div>
        </div>
    </section>

    <div class="mn-shot">@include('site.visuals.'.$solution['visual'])</div>

    <section class="mn-section" style="margin-top:clamp(48px,7vw,88px)">
        <div class="mn-narrow">
            <h2>Overview</h2>
            <div class="mn-prose">@foreach ($solution['overview'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>A setup that works</h2>
            <ol class="mn-steps">
                @foreach ($solution['setup'] as [$title, $text])<li><div><b>{{ $title }}</b><span>{{ $text }}</span></div></li>@endforeach
            </ol>
            @if (! empty($solution['later']))
                <p class="mn-group" style="margin-top:28px">Coming soon</p>
                <p class="mn-intro" style="margin:0">{{ implode(' · ', $solution['later']) }}</p>
            @endif
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Features used</h2>
            <ul class="mn-rows">
                @foreach ($features as $slug => $f)
                    <li><a href="{{ route('site.feature', $slug) }}"><b>{{ $f['name'] }}@if (($f['status'] ?? 'live') === 'soon')<span class="soon">Soon</span>@endif</b><span>{{ $f['summary'] ?? $f['menu'] }}</span><i>Learn more →</i></a></li>
                @endforeach
            </ul>
        </div>
    </section>

    @if (! empty($solution['faqs']))
        <section class="mn-section">
            <div class="mn-narrow"><h2>Questions</h2>@include('site.partials.faq', ['faqs' => $solution['faqs']])</div>
        </section>
    @endif

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Set it up on your store</h2>
            <p>Install OrderOrbit Space and publish your first offer in a few minutes.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a></div>
        </div>
    </section>
</div>
@endsection
