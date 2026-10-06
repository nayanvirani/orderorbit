@extends('layouts.site')
@php($page = \App\Support\SiteContent::page('features'))
@php($d = $page['detail'])
@php($names = collect($templates)->pluck('name')->unique()->values())
@php($vars = ['name' => $feature['name'], 'count' => $names->count()])

@section('title', \App\Support\SiteContent::plain($feature['seo_title']))
@section('description', \App\Support\SiteContent::plain($feature['seo_description']))

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="stack lg measure" style="max-width:860px">
            <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ $d['breadcrumb_home'] }}</a><span aria-hidden="true">/</span><a href="{{ route('site.features') }}">{{ $page['breadcrumb'] }}</a><span aria-hidden="true">/</span><span>{{ $feature['name'] }}</span></nav>
            <span class="eyebrow">{{ $feature['name'] }}</span>
            <h1 style="font-size:clamp(38px,4.6vw,60px)">{{ site_md($feature['h1']) }}</h1>
            <p class="lead" style="font-size:19px">{{ site_md($feature['hero']) }}</p>
            <div class="row">
                <a class="btn primary" href="{{ site_url($d['install']['href']) }}" data-event="cta_install_clicked">{{ $d['install']['label'] }}</a>
                @if ($names->isNotEmpty())<a class="btn secondary" href="{{ route('site.templates') }}#{{ $feature['slug'] }}">{{ site_md($d['templates_button'], $vars) }}</a>@endif
                @if (isset(\App\Support\Content::docs()[$feature['slug']]))<a class="btn secondary" href="{{ route('site.docs', $feature['slug']) }}">{{ $d['guide_button'] }}</a>@endif
            </div>
        </div>
    </div>
</section>

<div class="visual-band"><div class="shot">@include('site.visuals.'.$feature['slug'])</div></div>

@if (! empty($feature['overview']) || ! empty($feature['benefits']))
<section class="section">
    <div class="wrap split top">
        <div class="narrow stack">
            <span class="kicker">{{ $d['overview_eyebrow'] }}</span>
            <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['overview_title'], $vars) }}</h2>
            <div class="prose" style="font-size:17px">@foreach ($feature['overview'] ?? [] as $paragraph)<p>{{ site_md($paragraph) }}</p>@endforeach</div>
        </div>
        <div class="wide grid" style="--min:280px">
            @foreach ($feature['benefits'] ?? [] as $benefit)
                <div class="card reveal"><b class="card-title" style="font-size:19px">{{ $benefit[0] ?? '' }}</b><span class="card-text">{{ site_md($benefit[1] ?? '') }}</span></div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="section white">
    <div class="wrap stack xl">
        <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['how_title'], $vars) }}</h2>
        <ol class="grid" style="--min:260px;margin:0;padding:0;list-style:none">
            @foreach ($feature['steps'] as $step)
                <li class="card soft reveal"><span class="num">{{ $d['step'] }} {{ $loop->iteration }}</span><span style="font-size:17px;line-height:1.5">{{ site_md($step) }}</span></li>
            @endforeach
        </ol>
        @if (! empty($feature['example']['text']))
            <div class="card lavender" style="flex-direction:row;flex-wrap:wrap;gap:12px 32px;align-items:center">
                <b style="font-size:18px;color:var(--c-heading)">{{ $d['example'] }} {{ rtrim($feature['example']['title'], '.') }}</b>
                <span class="card-text" style="flex:1 1 520px;font-size:16px">{{ site_md($feature['example']['text']) }}</span>
            </div>
        @endif
    </div>
</section>

@if ($names->isNotEmpty())
<section class="section">
    <div class="wrap stack lg">
        <div class="row between">
            <div class="stack" style="gap:10px"><h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['templates_title'], $vars) }}</h2><span class="muted" style="font-size:17px">{{ site_md($d['templates_text'], $vars) }}</span></div>
            <a class="link" href="{{ route('site.templates') }}">{{ $d['templates_link'] }}</a>
        </div>
        <div class="chips">@foreach ($names as $name)<span class="chip static">{{ $name }}</span>@endforeach</div>
    </div>
</section>
@endif

@if (! empty($feature['faqs']))
<section class="section white">
    <div class="wrap split top">
        <div class="narrow"><h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['faq_title'], $vars) }}</h2></div>
        <div class="wide">@include('site.partials.faq', ['faqs' => $feature['faqs']])</div>
    </div>
</section>
@endif

<section class="section">
    <div class="wrap stack lg">
        <h2 style="font-size:32px">{{ site_md($d['related_title'], $vars) }}</h2>
        <div class="grid">
            @foreach ($related as $slug => $r)
                <a class="card reveal" href="{{ route('site.feature', $slug) }}"><span class="card-title" style="font-size:18px">{{ $r['name'] }}</span><span class="card-text">{{ $r['summary'] ?? $r['menu'] }}</span></a>
            @endforeach
        </div>
    </div>
</section>

@include('site.partials.cta', ['cta' => ['title' => $d['cta_title'], 'text' => '', 'primary' => $d['cta_button']], 'vars' => $vars])
@endsection
