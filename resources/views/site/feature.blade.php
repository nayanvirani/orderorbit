@extends('layouts.site')

@section('title', $feature['seo_title'])
@section('description', $feature['seo_description'])

@php
    $toc = ['problem' => 'The problem', 'how' => 'How it works'] + ($templates ? ['templates' => 'Templates'] : []) + ['features' => 'Features', 'example' => 'Example setup', 'results' => 'Results', 'faq' => 'FAQ'];
@endphp

@section('content')
<section class="ed-hero sky">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">Home</a><x-icon name="chev-right"/><a href="{{ route('site.features') }}">Features</a><x-icon name="chev-right"/><span>{{ $feature['name'] }}</span></nav>
        <span class="eyebrow"><span class="dot"></span>{{ $feature['eyebrow'] }}<span class="dot"></span></span>
        <h1>{{ $feature['h1'] }}</h1>
        <p class="lead">{{ $feature['hero'] }}</p>
        <div class="ctas">
            <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a>
            @isset($feature['secondary_cta'])
                <a class="btn lg" href="{{ $feature['secondary_cta']['href'] }}">{{ $feature['secondary_cta']['label'] }}</a>
            @else
                <a class="btn lg" href="{{ route('site.pricing') }}">See Pricing</a>
            @endisset
        </div>
        <div class="screen">@include('site.visuals.'.$feature['slug'])</div>
    </div>
</section>

<section class="section" style="padding-top:40px">
    <div class="wrap doc">
        <nav class="doc-rail" aria-label="On this page">
            <p>On this page</p>
            @foreach ($toc as $id => $label)<a href="#{{ $id }}" data-toc>{{ $label }}</a>@endforeach
        </nav>

        <div class="doc-body">
            <section id="problem">
                <div class="problem reveal">
                    <div>
                        <span class="tag"><x-icon name="alert"/>The problem</span>
                        <h2>{{ $feature['problem'][0] }}</h2>
                        <p>{{ $feature['problem'][1] }}</p>
                    </div>
                    <div>
                        <span class="eyebrow"><span class="dot"></span>How OrderOrbit fixes it</span>
                        <ul class="bullet-list">
                            @foreach (array_slice($feature['grid'], 0, 3) as $point)<li><x-icon name="check"/>{{ $point }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            </section>

            <section id="how">
                <div class="section-head reveal">
                    <span class="eyebrow"><span class="dot"></span>How it works</span>
                    <h2>{{ $feature['steps_heading'] ?? 'Live in '.count($feature['steps']).' steps.' }}</h2>
                </div>
                <ol class="steps">
                    @foreach ($feature['steps'] as $step)
                        <li class="step reveal"><span class="num">{{ $loop->iteration }}</span><div><h3>{{ $step }}</h3></div></li>
                    @endforeach
                </ol>
            </section>

            @if ($templates)
                <section id="templates">
                    <div class="section-head reveal">
                        <span class="eyebrow"><span class="dot"></span>Templates</span>
                        <h2>{{ count($templates) }} {{ \Illuminate\Support\Str::plural('template', count($templates)) }}, <em>ready to customise.</em></h2>
                        <p class="lead">Start from a proven design, then match your brand colours, fonts and spacing.</p>
                    </div>
                    <div class="tpl-grid">
                        @foreach ($templates as $tpl)
                            <a class="tpl reveal" href="{{ route('site.templates', ['type' => $tpl['type']]) }}" style="text-decoration:none">
                                <div class="thumb"><div style="filter:hue-rotate({{ [0, 28, -24, 52, -46][$loop->index % 5] }}deg)">@include('site.partials.thumb', ['type' => $tpl['type'], 'v' => $loop->index])</div></div>
                                <div class="meta"><b>{{ $tpl['name'] }}</b><span><span class="pill">{{ $tpl['label'] }}</span>@if ($surfaces[$tpl['surface']] !== $tpl['label'])<span class="pill gray">{{ $surfaces[$tpl['surface']] }}</span>@endif</span></div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section id="features">
                <div class="section-head reveal">
                    <span class="eyebrow"><span class="dot"></span>Features</span>
                    <h2>Everything you need, <em>nothing that fights your theme.</em></h2>
                </div>
                <ol class="num-list cols">
                    @foreach ($feature['grid'] as $item)<li class="reveal">{{ $item }}</li>@endforeach
                </ol>
                @isset($feature['note'])
                    <div class="note reveal" style="margin-top:28px"><x-icon name="info"/><span>{{ $feature['note'] }}</span></div>
                @endisset
            </section>

            <section id="example">
                <div class="example-card reveal">
                    <div>
                        <span class="label">Example setup</span>
                        <h3>{{ $feature['example']['title'] }}</h3>
                        <p>{{ $feature['example']['text'] }}</p>
                    </div>
                    <div class="flow-wrap">@include('site.diagrams.flow', ['steps' => $feature['example']['flow'], 'vertical' => true])</div>
                </div>
            </section>

            <section id="results">
                <div class="split">
                    <div class="split-copy reveal">
                        <span class="eyebrow"><span class="dot"></span>Analytics</span>
                        <h2>See exactly <em>what it earns.</em></h2>
                        <p class="lead">Every {{ strtolower($feature['name']) }} view, interaction and purchase is tracked with consent-aware analytics, so you can compare experiences with one set of numbers — and A/B test the next idea.</p>
                        <a class="link-arrow" href="{{ route('site.feature', 'analytics') }}">Explore Analytics <x-icon name="arrow"/></a>
                    </div>
                    @include('site.partials.metrics', ['title' => $feature['name'], 'metrics' => $feature['metrics'], 'id' => $feature['slug']])
                </div>
            </section>

            <section id="faq">
                <div class="section-head reveal">
                    <span class="eyebrow"><span class="dot"></span>FAQ</span>
                    <h2>{{ $feature['name'] }}, <em>answered.</em></h2>
                </div>
                @include('site.partials.faq', ['faqs' => $feature['faqs']])
            </section>

            <section>
                <div class="section-head reveal" style="margin-bottom:28px">
                    <span class="eyebrow"><span class="dot"></span>Works even better with</span>
                </div>
                <div class="bento">
                    @foreach ($related as $slug => $r)
                        <a class="b-tile go-arrow w2 reveal" href="{{ route('site.feature', $slug) }}">
                            <span class="tile-tag">{{ $r['eyebrow'] }}</span>
                            <h3>{{ $r['name'] }}</h3>
                            <p>{{ $r['h1'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
