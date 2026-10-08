@extends('layouts.site')
@php($page = \App\Support\SiteContent::page('solutions'))
@php($d = $page['detail'])
@php($vars = ['name' => $solution['name']])

@section('title', \App\Support\SiteContent::plain($solution['name']).' | Growvia')
@section('description', \App\Support\SiteContent::plain($solution['seo_description']))

@section('content')
<section class="glow-hero feature-hero">
    <div class="wrap">
        <div class="stack lg measure" style="max-width:860px">
            <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ $d['breadcrumb_home'] }}</a><span aria-hidden="true">/</span><a href="{{ route('site.solutions') }}">{{ $page['eyebrow'] }}</a><span aria-hidden="true">/</span><span>{{ $solution['name'] }}</span></nav>
            <span class="eyebrow">{{ site_md($d['eyebrow'], $vars) }}</span>
            <h1 style="font-size:clamp(38px,4.6vw,60px)">{{ site_md($solution['h1']) }}</h1>
            <p class="lead" style="font-size:19px">{{ site_md($solution['hero']) }}</p>
            <div class="row">
                <a class="btn primary" href="{{ site_url($d['install']['href']) }}" data-event="cta_install_clicked">{{ $d['install']['label'] }}</a>
                <a class="btn secondary" href="{{ site_url($d['pricing']['href']) }}">{{ $d['pricing']['label'] }}</a>
            </div>
        </div>
        <div class="stage">@include('site.visuals.'.$solution['visual'])</div>
    </div>
</section>

<section class="section">
    <div class="wrap split top">
        <div class="narrow stack">
            <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['overview_title'], $vars) }}</h2>
            <div class="prose" style="font-size:17px">@foreach ($solution['overview'] as $paragraph)<p>{{ site_md($paragraph) }}</p>@endforeach</div>
        </div>
        <div class="wide stack lg">
            <h2 style="font-size:clamp(24px,2.4vw,30px)">{{ site_md($d['setup_title'], $vars) }}</h2>
            <ol class="grid" style="--min:260px;margin:0;padding:0;list-style:none">
                @foreach ($solution['setup'] as $step)
                    <li class="card reveal"><span class="step-num">{{ $loop->iteration }}</span><b class="card-title" style="font-size:18px">{{ $step[0] ?? '' }}</b><span class="card-text">{{ site_md($step[1] ?? '') }}</span></li>
                @endforeach
            </ol>
        </div>
    </div>
</section>

<section class="section white">
    <div class="wrap stack lg">
        <h2 style="font-size:32px">{{ site_md($d['features_title'], $vars) }}</h2>
        <div class="grid">
            @foreach ($features as $slug => $f)
                <a class="card soft reveal" href="{{ route('site.feature', $slug) }}"><span class="icon-tile"><x-icon :name="$f['icon']"/></span><span class="card-title" style="font-size:18px">{{ $f['name'] }}</span><span class="card-text">{{ $f['summary'] ?? $f['menu'] }}</span><span class="card-link">{{ $d['learn_more'] }}</span></a>
            @endforeach
        </div>
    </div>
</section>

@if (! empty($solution['faqs']))
<section class="section">
    <div class="wrap split top">
        <div class="narrow"><h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($d['faq_title'], $vars) }}</h2></div>
        <div class="wide">@include('site.partials.faq', ['faqs' => $solution['faqs']])</div>
    </div>
</section>
@endif

@include('site.partials.cta', ['cta' => ['title' => $d['cta_title'], 'text' => $d['cta_text'], 'primary' => $d['install']], 'vars' => $vars])
@endsection
