@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('help'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
<section class="page-hero dark center">
    <div class="wrap">
        <span class="eyebrow">{{ $c['eyebrow'] }}</span>
        <h1>{{ site_md($c['title']) }}</h1>
        <p class="lead">{{ site_md($c['lead']) }}</p>
        <label class="search" role="search"><span class="sr-only">{{ $c['search_label'] }}</span><input type="search" placeholder="{{ $c['search_placeholder'] }}" aria-label="{{ $c['search_label'] }}" data-help-search></label>
        @if ($c['guides_link']['label'])<a class="link" style="color:var(--heading)" href="{{ site_url($c['guides_link']['href']) }}">{{ $c['guides_link']['label'] }}</a>@endif
    </div>
</section>

<section class="section" style="padding-bottom:40px">
    <div class="wrap stack lg">
        <h2 style="font-size:32px">{{ site_md($c['topics_title']) }}</h2>
        <div class="grid" style="--min:280px;gap:16px">
            @foreach ($helpCategories as $cat)
                <a class="card" style="padding:22px;gap:6px" href="#{{ $cat['slug'] }}"><b class="card-title" style="font-size:18px">{{ $cat['name'] }}</b><span class="card-text">{{ site_md($cat['text']) }}</span><span class="small dim">{{ \App\Support\SiteContent::plain($c['articles'], ['count' => count($cat['articles'])]) }}</span></a>
            @endforeach
        </div>
    </div>
</section>

@foreach ($helpCategories as $cat)
    <section class="section tight" id="{{ $cat['slug'] }}" data-help-item style="scroll-margin-top:96px">
        <div class="wrap split top">
            <div class="narrow stack"><h2 style="font-size:clamp(24px,2.6vw,32px)">{{ $cat['name'] }}</h2><p class="muted">{{ site_md($cat['text']) }}</p></div>
            <div class="wide">@include('site.partials.faq', ['faqs' => $cat['articles'], 'openFirst' => false])</div>
        </div>
    </section>
@endforeach

<section class="section tight" data-help-empty hidden>
    <div class="wrap stack center">
        <h2 style="font-size:28px">{{ \App\Support\SiteContent::plain($c['empty_title'], ['q' => '']) }}<span data-help-q hidden></span></h2>
        <p class="muted">{{ site_md($c['empty_text']) }}</p>
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta'], 'class' => ''])
@endsection
