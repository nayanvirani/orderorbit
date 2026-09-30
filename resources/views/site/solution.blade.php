@extends('layouts.site')

@section('title', $solution['name'].' | Shopify CRO for '.$solution['name'].' | OrderOrbit Space')
@section('description', $solution['seo_description'])

@section('content')
<section class="ed-hero sky">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">Home</a><x-icon name="chev-right"/><a href="{{ route('site.solutions') }}">Solutions</a><x-icon name="chev-right"/><span>{{ $solution['name'] }}</span></nav>
        <span class="eyebrow"><span class="dot"></span>{{ $solution['name'] }}<span class="dot"></span></span>
        <h1>{{ $solution['h1'] }}</h1>
        <p class="lead">{{ $solution['hero'] }}</p>
        <div class="ctas">
            <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
            <a class="btn lg" href="#setup">See the setup</a>
        </div>
        <div class="screen">@include('site.visuals.'.$solution['visual'])</div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="problem reveal">
            <div>
                <span class="tag"><x-icon name="alert"/>Industry problem</span>
                <h2>{{ $solution['problem'][0] }}</h2>
                <p>{{ $solution['problem'][1] }}</p>
            </div>
            <div class="stack-art" aria-hidden="true" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                @foreach (array_slice($features, 0, 4, true) as $f)
                    <div class="app" style="border-style:solid;color:var(--text)"><x-icon :name="$f['icon']" style="color:var(--gold)"/>{{ $f['name'] }}<div class="line" style="width:80%"></div></div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="section invert sky" id="setup">
    <div class="wrap split">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>Recommended setup</span>
            <h2>The OrderOrbit Space setup for <em>{{ $solution['name'] }}.</em></h2>
            <ol class="num-list" style="margin-top:28px">
                @foreach ($solution['setup'] as $item)<li>{{ $item }}</li>@endforeach
            </ol>
        </div>
        <div class="reveal">@include('site.diagrams.orbit', ['active' => $solution['loop']])</div>
    </div>
</section>

<section class="section">
    <div class="wrap chapter">
        <div class="chapter-side reveal">
            <span class="eyebrow"><span class="dot"></span>Key features</span>
            <h2>What <em>you'll use.</em></h2>
        </div>
        <div class="bento">
            @foreach ($features as $slug => $f)
                <a class="b-tile go-arrow w3 reveal" href="{{ route('site.feature', $slug) }}">
                    <span class="tile-tag">{{ $f['eyebrow'] }}</span>
                    <h3>{{ $f['name'] }}</h3>
                    <p>{{ $f['hero'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="section alt">
    <div class="wrap">
        <div class="example-card reveal">
            <div>
                <span class="label">Example use case</span>
                <h3>{{ $solution['example']['title'] }}</h3>
                <p>{{ $solution['example']['text'] }}</p>
            </div>
            <div class="flow-wrap">@include('site.diagrams.flow', ['steps' => $solution['example']['flow'], 'vertical' => true])</div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap faq-layout">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>FAQ</span>
            <h2>{{ $solution['name'] }}, <em>answered.</em></h2>
        </div>
        @include('site.partials.faq', ['faqs' => $solution['faqs']])
    </div>
</section>

@include('site.partials.cta')
@endsection
