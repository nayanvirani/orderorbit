@extends('layouts.site')

@section('title', $solution['name'].' | Shopify CRO for '.$solution['name'].' | OrderOrbit')
@section('description', $solution['seo_description'])

@section('content')
<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">Home</a><x-icon name="chev-right"/><a href="{{ route('site.solutions') }}">Solutions</a><x-icon name="chev-right"/><span>{{ $solution['name'] }}</span></nav>
            <span class="eyebrow"><span class="dot"></span>{{ $solution['name'] }}</span>
            <h1>{{ $solution['h1'] }}</h1>
            <p class="lead">{{ $solution['hero'] }}</p>
            <div class="ctas">
                <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install on Shopify</a>
                <a class="btn lg" href="#setup">See the setup <x-icon name="arrow"/></a>
            </div>
        </div>
        @include('site.visuals.'.$solution['visual'])
    </div>
</section>

<section class="section tight">
    <div class="wrap">
        <div class="problem reveal">
            <div>
                <span class="tag"><x-icon name="alert"/>Industry problem</span>
                <h2>{{ $solution['problem'][0] }}</h2>
                <p>{{ $solution['problem'][1] }}</p>
            </div>
            <div class="stack-art" aria-hidden="true" style="grid-template-columns:repeat(2,1fr)">
                @foreach (array_slice($features, 0, 4, true) as $f)
                    <div class="app" style="border-style:solid;border-color:#e3deff;color:var(--ink)"><x-icon :name="$f['icon']" style="color:var(--indigo)"/>{{ $f['name'] }}<div class="line w80"></div></div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="section dark" id="setup">
    <div class="wrap split">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>Recommended setup</span>
            <h2>The OrderOrbit setup for {{ $solution['name'] }}.</h2>
            <ul class="bullet-list">
                @foreach ($solution['setup'] as $item)<li><x-icon name="check-circle"/>{{ $item }}</li>@endforeach
            </ul>
        </div>
        <div class="reveal">@include('site.diagrams.orbit', ['active' => $solution['loop']])</div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Key features</span>
            <h2>What you'll use.</h2>
        </div>
        <div class="grid three">
            @foreach ($features as $slug => $f)
                <a class="card reveal" href="{{ route('site.feature', $slug) }}">
                    <div class="icon-badge"><x-icon :name="$f['icon']"/></div>
                    <h3>{{ $f['name'] }}</h3>
                    <p>{{ $f['hero'] }}</p>
                    <span class="more">Explore {{ $f['name'] }} <x-icon name="arrow"/></span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="section tint">
    <div class="wrap">
        <div class="example-card reveal">
            <div>
                <span class="label">Example use case</span>
                <h3>{{ $solution['example']['title'] }}</h3>
                <p>{{ $solution['example']['text'] }}</p>
            </div>
            <div class="flow-wrap">@include('site.diagrams.flow', ['steps' => $solution['example']['flow']])</div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head reveal"><h2>{{ $solution['name'] }} FAQ</h2></div>
        @include('site.partials.faq', ['faqs' => $solution['faqs']])
    </div>
</section>

@include('site.partials.cta')
@endsection
