@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('about'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
<section class="page-hero">
    <div class="wrap split" style="align-items:flex-end">
        <div class="stack lg" style="flex:1 1 640px">
            <span class="eyebrow">{{ $c['eyebrow'] }}</span>
            <h1>{{ site_md($c['title']) }}</h1>
        </div>
        <div class="prose" style="flex:1 1 420px">@foreach ($c['paragraphs'] as $paragraph)<p>{{ site_md($paragraph) }}</p>@endforeach</div>
    </div>
</section>

<section class="section">
    <div class="wrap stack xl">
        <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($c['principles_title']) }}</h2>
        <div class="grid" style="--min:380px">
            @foreach ($c['principles'] as $p)
                <div class="card reveal"><span class="num">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><b class="card-title">{{ $p[0] ?? '' }}</b><span class="card-text" style="font-size:16px">{{ site_md($p[1] ?? '') }}</span></div>
            @endforeach
            <div class="card ink reveal"><span class="num" style="color:var(--c-dark-accent)">{{ $c['independent']['label'] }}</span><b class="card-title">{{ $c['independent']['title'] }}</b><span class="card-text" style="font-size:16px">{{ site_md($c['independent']['text']) }}</span></div>
        </div>
    </div>
</section>

@php($ct = $c['contact'])
<section class="section white">
    <div class="wrap stack center">
        <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($ct['title']) }}</h2>
        <p class="lead" style="font-size:18px">{{ site_md($ct['text']) }}</p>
        <div class="row center" style="margin-top:8px">
            <a class="btn primary" href="{{ site_url($ct['primary']['href']) }}">{{ $ct['primary']['label'] }}</a>
            <a class="btn secondary" href="{{ site_url($ct['secondary']['href']) }}" data-event="cta_install_clicked">{{ $ct['secondary']['label'] }}</a>
        </div>
    </div>
</section>
@endsection
