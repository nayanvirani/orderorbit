@extends('layouts.site')
@php($page = \App\Support\SiteContent::page('features'))
@php($d = $page['detail'])
@php($names = collect($templates)->pluck('name')->unique()->values())
@php($vars = ['name' => $feature['name'], 'count' => $names->count()])
@php($benefitIcons = ['check-circle', 'layers', 'target', 'sparkle', 'zap', 'shield', 'chart', 'heart'])

@section('title', \App\Support\SiteContent::plain($feature['seo_title']))
@section('description', \App\Support\SiteContent::plain($feature['seo_description']))

@section('content')
<section class="glow-hero feature-hero">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ $d['breadcrumb_home'] }}</a><span aria-hidden="true">/</span><a href="{{ route('site.features') }}">{{ $page['breadcrumb'] }}</a><span aria-hidden="true">/</span><span>{{ $feature['name'] }}</span></nav>
        <div class="row" style="gap:10px"><span class="icon-tile" style="width:44px;height:44px"><x-icon :name="$feature['icon']"/></span><span class="eyebrow">{{ $feature['name'] }}</span></div>
        <h1>{{ site_md($feature['h1']) }}</h1>
        <p class="lead" style="max-width:760px">{{ site_md($feature['hero']) }}</p>
        <div class="row">
            <a class="btn primary" href="{{ site_url($d['install']['href']) }}" data-event="cta_install_clicked">{{ $d['install']['label'] }}</a>
            @if ($names->isNotEmpty())<a class="btn secondary" href="{{ route('site.templates') }}#{{ $feature['slug'] }}">{{ site_md($d['templates_button'], $vars) }}</a>@endif
            @if (isset(\App\Support\Content::docs()[$feature['slug']]))<a class="btn secondary" href="{{ route('site.docs', $feature['slug']) }}">{{ $d['guide_button'] }}</a>@endif
        </div>
        @if (! empty($feature['benefits']))
            <div class="highlights">@foreach ($feature['benefits'] as $benefit)@if (! empty($benefit[0]))<span><x-icon name="check"/>{{ $benefit[0] }}</span>@endif @endforeach</div>
        @endif
        <div class="stage">@include('site.visuals.'.$feature['slug'])</div>
    </div>
</section>

@if (! empty($feature['overview']) || ! empty($feature['benefits']))
<section class="section">
    <div class="wrap split top">
        <div class="narrow stack">
            <span class="kicker">{{ $d['overview_eyebrow'] }}</span>
            <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($feature['problem'][0] ?? $d['overview_title'], $vars) }}</h2>
            <div class="prose" style="font-size:17px">@foreach ($feature['overview'] ?? [] as $paragraph)<p>{{ site_md($paragraph) }}</p>@endforeach</div>
        </div>
        <div class="wide grid" style="--min:260px">
            @foreach ($feature['benefits'] ?? [] as $benefit)
                <div class="card benefit reveal"><span class="icon-tile"><x-icon :name="$benefitIcons[$loop->index % count($benefitIcons)]"/></span><b class="card-title" style="font-size:19px">{{ $benefit[0] ?? '' }}</b><span class="card-text">{{ site_md($benefit[1] ?? '') }}</span></div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if (! empty($feature['metrics']))
<section class="section dark tight">
    <div class="wrap split">
        <div class="stack" style="flex:1 1 320px">
            <span class="kicker">{{ $d['example_results'] }}</span>
            <h2 style="font-size:clamp(26px,2.8vw,36px)">{{ site_md($feature['example']['title'] ?? $feature['name']) }}</h2>
            <p class="muted">{{ site_md($d['example_note']) }}</p>
        </div>
        <div class="results" style="flex:2 1 600px">@foreach ($feature['metrics'] as $m)<div class="reveal"><span>{{ $m[0] ?? '' }}</span><b>{{ $m[1] ?? '' }}</b></div>@endforeach</div>
    </div>
</section>
@endif

<section class="section">
    <div class="wrap stack xl">
        <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['how_title'], $vars) }}</h2>
        <ol class="timeline">@foreach ($feature['steps'] as $step)<li class="reveal">{{ site_md($step) }}</li>@endforeach</ol>
        @if (! empty($feature['example']['text']))
            <div class="card lavender" style="flex-direction:row;flex-wrap:wrap;gap:12px 32px;align-items:center;padding:28px 32px">
                <b style="font-size:18px;color:var(--c-heading)">{{ $d['example'] }} {{ rtrim($feature['example']['title'], '.') }}</b>
                <span class="card-text" style="flex:1 1 520px;font-size:16px">{{ site_md($feature['example']['text']) }}</span>
            </div>
        @endif
    </div>
</section>

@if ($names->isNotEmpty())
<section class="section white">
    <div class="wrap stack lg">
        <div class="row between">
            <div class="stack" style="gap:10px"><h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['templates_title'], $vars) }}</h2><span class="muted" style="font-size:17px">{{ site_md($d['templates_text'], $vars) }}</span></div>
            <a class="link" href="{{ route('site.templates') }}#{{ $feature['slug'] }}">{{ $d['templates_link'] }}</a>
        </div>
        {{-- The templates themselves are only shown in the app. --}}
        <div class="tpl-locked">
            <p>{{ site_md($d['templates_locked'], $vars) }}</p>
            <a class="btn primary" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">{{ $d['templates_install'] }}</a>
        </div>
    </div>
</section>
@endif

@if (! empty($feature['faqs']))
<section class="section">
    <div class="wrap split top">
        <div class="narrow"><h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['faq_title'], $vars) }}</h2></div>
        <div class="wide">@include('site.partials.faq', ['faqs' => $feature['faqs']])</div>
    </div>
</section>
@endif

<section class="section white">
    <div class="wrap stack lg">
        <h2 style="font-size:32px">{{ site_md($d['related_title'], $vars) }}</h2>
        <div class="grid">
            @foreach ($related as $slug => $r)
                <a class="card reveal" href="{{ route('site.feature', $slug) }}"><span class="icon-tile"><x-icon :name="$r['icon']"/></span><span class="card-title" style="font-size:18px">{{ $r['name'] }}</span><span class="card-text">{{ $r['summary'] ?? $r['menu'] }}</span><span class="card-link">{{ $page['learn_more'] }}</span></a>
            @endforeach
        </div>
    </div>
</section>

@include('site.partials.cta', ['cta' => ['title' => $d['cta_title'], 'text' => '', 'primary' => $d['cta_button']], 'vars' => $vars, 'class' => ''])
@endsection
