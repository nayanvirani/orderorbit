@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('resources'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
@include('site.partials.page-hero', ['c' => $c])

<section class="section" style="padding-top:72px">
    <div class="wrap grid">
        @foreach ($c['cards'] as $card)
            <a class="card reveal" href="{{ site_url($card['href']) }}"><span class="icon-tile"><x-icon :name="['help', 'book', 'grid', 'orbit', 'message'][$loop->index % 5]"/></span><span class="card-title">{{ $card['title'] }}</span><span class="card-text">{{ site_md($card['text']) }}</span><span class="card-link">{{ $card['link'] }}</span></a>
        @endforeach
    </div>
</section>

<section class="section white">
    <div class="wrap split top">
        <div class="narrow stack lg">
            <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($c['faq_title']) }}</h2>
            <a class="btn secondary" style="align-self:flex-start" href="{{ site_url($c['faq_button']['href']) }}">{{ $c['faq_button']['label'] }}</a>
        </div>
        <div class="wide">@include('site.partials.faq', ['faqs' => collect($helpCategories)->flatMap(fn ($cat) => array_slice($cat['articles'], 0, 1))->take(6)->all()])</div>
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta'], 'class' => ''])
@endsection
