@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('solutions'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
@include('site.partials.page-hero', ['c' => $c])

<section class="section" style="padding-top:72px">
    <div class="wrap grid" style="--min:520px;gap:24px">
        @foreach ($solutions as $slug => $s)
            <a class="card lg {{ $loop->last ? 'ink' : '' }} reveal" href="{{ route('site.solution', $slug) }}">
                <span class="icon-tile" style="width:52px;height:52px"><x-icon :name="$s['icon']"/></span>
                <span class="card-title" style="font-size:26px">{{ $s['name'] }}</span>
                <span class="card-text" style="font-size:17px">{{ site_md($s['hero']) }}</span>
                <span class="tags">@foreach (array_slice($s['features'], 0, 3) as $fs)@php($fn = \App\Support\Content::features()[$fs]['name'] ?? null)@if ($fn)<span class="tag">{{ $fn }}</span>@endif @endforeach</span>
                <span class="card-link">{{ $c['card_link'] }}</span>
            </a>
        @endforeach
    </div>
</section>

@php($st = $c['start'])
<section class="section white">
    <div class="wrap split">
        <div class="stack lg">
            <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($st['title']) }}</h2>
            <p class="lead" style="font-size:18px">{{ site_md($st['text']) }}</p>
            <div class="row">
                <a class="btn primary" href="{{ site_url($st['primary']['href']) }}" data-event="cta_install_clicked">{{ $st['primary']['label'] }}</a>
                <a class="btn secondary" href="{{ site_url($st['secondary']['href']) }}">{{ $st['secondary']['label'] }}</a>
            </div>
        </div>
        <div class="grid" style="--min:200px;gap:16px">
            @foreach ($st['plan'] as $step)
                <div class="card lavender" style="padding:24px;gap:8px"><span class="num">{{ $step['when'] }}</span><b style="font-size:17px;color:var(--c-heading)">{{ $step['title'] }}</b><span class="card-text" style="font-size:14px">{{ $step['text'] }}</span></div>
            @endforeach
        </div>
    </div>
</section>
@endsection
