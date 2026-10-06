@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('how'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
@include('site.partials.page-hero', ['c' => $c])

<section class="section" style="padding-top:88px">
    <div class="wrap">
        <ol class="steps-list">
            @foreach ($c['steps'] as $step)
                <li class="reveal"><span class="step-num">{{ $loop->iteration }}</span><div><b>{{ $step[0] ?? '' }}</b><span>{{ site_md($step[1] ?? '') }}</span></div></li>
            @endforeach
        </ol>
    </div>
</section>

<section class="section white">
    <div class="wrap stack xl">
        <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($c['store']['title']) }}</h2>
        <div class="grid" style="--min:340px">
            @foreach ($c['store']['cards'] as $card)
                <div class="card soft"><b class="card-title" style="font-size:19px">{{ $card[0] ?? '' }}</b><span class="card-text" style="font-size:16px">{{ site_md($card[1] ?? '') }}</span></div>
            @endforeach
        </div>
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta'], 'class' => ''])
@endsection
