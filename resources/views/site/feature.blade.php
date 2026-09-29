@extends('layouts.site')

@section('title', $feature['seo_title'])
@section('description', $feature['seo_description'])

@section('content')
{{-- Hero --}}
<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">Home</a><x-icon name="chev-right"/><a href="{{ route('site.features') }}">Features</a><x-icon name="chev-right"/><span>{{ $feature['name'] }}</span></nav>
            <span class="eyebrow"><span class="dot"></span>{{ $feature['eyebrow'] }}</span>
            <h1>{{ $feature['h1'] }}</h1>
            <p class="lead">{{ $feature['hero'] }}</p>
            <div class="ctas">
                <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install on Shopify</a>
                @isset($feature['secondary_cta'])
                    <a class="btn lg" href="{{ $feature['secondary_cta']['href'] }}">{{ $feature['secondary_cta']['label'] }} <x-icon name="arrow"/></a>
                @else
                    <a class="btn lg" href="{{ route('site.pricing') }}">See Pricing <x-icon name="arrow"/></a>
                @endisset
            </div>
        </div>
        @include('site.visuals.'.$feature['slug'])
    </div>
</section>

{{-- Problem --}}
<section class="section tight">
    <div class="wrap">
        <div class="problem reveal">
            <div>
                <span class="tag"><x-icon name="alert"/>The problem</span>
                <h2>{{ $feature['problem'][0] }}</h2>
                <p>{{ $feature['problem'][1] }}</p>
            </div>
            <div class="card" style="background:var(--tint);border-color:#e3deff;box-shadow:none">
                <div class="icon-badge"><x-icon :name="$feature['icon']"/></div>
                <h3>How OrderOrbit fixes it</h3>
                <ul class="bullet-list">
                    @foreach (array_slice($feature['grid'], 0, 3) as $point)<li><x-icon name="check-circle"/>{{ $point }}</li>@endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- How it works --}}
<section class="section">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>How it works</span>
            <h2>{{ $feature['steps_heading'] ?? 'Live in '.count($feature['steps']).' steps.' }}</h2>
        </div>
        <div class="steps">
            @foreach ($feature['steps'] as $step)
                <div class="step reveal"><span class="num">{{ $loop->iteration }}</span><h3>{{ $step }}</h3></div>
            @endforeach
        </div>
    </div>
</section>

{{-- Template previews --}}
@if ($templates)
<section class="section tint">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Templates</span>
            <h2>{{ count($templates) }} {{ \Illuminate\Support\Str::plural('template', count($templates)) }}, ready to customise.</h2>
            <p class="lead">Start from a proven design, then match your brand colours, fonts and spacing.</p>
        </div>
        <div class="tpl-grid">
            @foreach ($templates as $tpl)
                <a class="tpl reveal" href="{{ route('site.templates', ['type' => $tpl['type']]) }}" style="text-decoration:none">
                    <div class="thumb"><div style="filter:hue-rotate({{ [0, 28, -24, 52, -46][$loop->index % 5] }}deg)">@include('site.partials.thumb', ['type' => $tpl['type'], 'v' => $loop->index])</div></div>
                    <div class="meta"><b>{{ $tpl['name'] }}</b><span><span class="pill">{{ $tpl['label'] }}</span><span class="pill gray">{{ $surfaces[$tpl['surface']] }}</span></span></div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Feature grid --}}
<section class="section">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Features</span>
            <h2>Everything you need, nothing that fights your theme.</h2>
        </div>
        <div class="checks">
            @foreach ($feature['grid'] as $item)
                <div class="check reveal"><span class="tick"><x-icon name="check"/></span>{{ $item }}</div>
            @endforeach
        </div>
        @isset($feature['note'])
            <div class="note reveal" style="margin-top:28px"><x-icon name="info"/><span>{{ $feature['note'] }}</span></div>
        @endisset
    </div>
</section>

{{-- Example setup --}}
<section class="section tint">
    <div class="wrap">
        <div class="example-card reveal">
            <div>
                <span class="label">Example setup</span>
                <h3>{{ $feature['example']['title'] }}</h3>
                <p>{{ $feature['example']['text'] }}</p>
            </div>
            <div class="flow-wrap">@include('site.diagrams.flow', ['steps' => $feature['example']['flow']])</div>
        </div>
    </div>
</section>

{{-- Results preview --}}
<section class="section">
    <div class="wrap split">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>Analytics</span>
            <h2>See exactly what it earns.</h2>
            <p class="lead">Every {{ strtolower($feature['name']) }} view, interaction and purchase is tracked with consent-aware analytics, so you can compare experiences with one set of numbers — and A/B test the next idea.</p>
            <a class="btn dark" href="{{ route('site.feature', 'analytics') }}">Explore Analytics <x-icon name="arrow"/></a>
        </div>
        @include('site.partials.metrics', ['title' => $feature['name'], 'metrics' => $feature['metrics'], 'id' => $feature['slug']])
    </div>
</section>

{{-- FAQ --}}
<section class="section tint">
    <div class="wrap">
        <div class="section-head reveal"><h2>{{ $feature['name'] }} FAQ</h2></div>
        @include('site.partials.faq', ['faqs' => $feature['faqs']])
    </div>
</section>

{{-- Related --}}
<section class="section tight">
    <div class="wrap">
        <div class="section-head left reveal" style="margin-bottom:28px"><h2 style="font-size:28px">Works even better with</h2></div>
        <div class="grid three">
            @foreach ($related as $slug => $r)
                <a class="card reveal" href="{{ route('site.feature', $slug) }}">
                    <div class="icon-badge soft"><x-icon :name="$r['icon']"/></div>
                    <h3>{{ $r['name'] }}</h3>
                    <p>{{ $r['h1'] }}</p>
                    <span class="more">Learn more <x-icon name="arrow"/></span>
                </a>
            @endforeach
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
