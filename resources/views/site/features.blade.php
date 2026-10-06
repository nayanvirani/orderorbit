@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('features'))
@php($features = \App\Support\Content::features())

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
<section class="page-hero glow-hero">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ $c['detail']['breadcrumb_home'] }}</a><span aria-hidden="true">/</span><span>{{ $c['breadcrumb'] }}</span></nav>
        <h1>{{ site_md($c['title']) }}</h1>
        <p class="lead">{{ site_md($c['lead']) }}</p>
        <div class="chips">
            @foreach ($c['groups'] as $group => $g)<a class="chip {{ $loop->first ? 'on' : '' }}" href="#{{ $group }}">{{ $g['tab'] }}</a>@endforeach
        </div>
    </div>
</section>

@foreach ($c['groups'] as $group => $g)
    @php($items = array_filter($features, fn ($f) => $f['group'] === $group))
    <section class="section {{ $loop->first ? '' : 'top-0' }}" id="{{ $group }}" style="scroll-margin-top:96px">
        <div class="wrap stack xl">
            <div class="row between">
                <div class="stack" style="gap:8px"><h2 style="font-size:clamp(28px,3vw,36px)">{{ site_md($g['title']) }}</h2><span class="muted" style="font-size:17px">{{ site_md($g['text']) }}</span></div>
            </div>
            <div class="grid" style="--min:{{ $group === 'checkout' ? '420px' : '300px' }}">
                @foreach ($items as $slug => $f)
                    @if ($group === 'convert')
                        <a class="card feature-card reveal" href="{{ route('site.feature', $slug) }}">
                            @include('site.partials.peek', ['slug' => $slug])
                            <div class="body"><span class="card-title">{{ $f['name'] }}</span><span class="card-text">{{ $f['summary'] ?? $f['menu'] }}</span><span class="card-link">{{ $c['learn_more'] }}</span></div>
                        </a>
                    @else
                        <a class="card {{ $group === 'checkout' ? 'ink lg' : '' }} reveal" href="{{ route('site.feature', $slug) }}">
                            <span class="icon-tile"><x-icon :name="$f['icon']"/></span>
                            <span class="card-title">{{ $f['name'] }}</span><span class="card-text">{{ $f['summary'] ?? $f['menu'] }}</span><span class="card-link">{{ $c['learn_more'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endforeach

@include('site.partials.cta', ['cta' => $c['cta'], 'class' => ''])
@endsection
