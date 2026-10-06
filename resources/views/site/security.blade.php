@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('security'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
@include('site.partials.page-hero', ['c' => $c])

@foreach ($c['groups'] as $group)
    <section class="section {{ $loop->first ? '' : 'top-0' }}">
        <div class="wrap stack lg">
            <h2 style="font-size:clamp(28px,3vw,36px)">{{ site_md($group['title']) }}</h2>
            <div class="grid">
                @foreach ($group['items'] as $item)
                    <div class="card reveal"><span class="icon-tile"><x-icon :name="['lock', 'shield', 'user', 'layers', 'eye', 'check', 'clock', 'trash'][($loop->parent->index * 4 + $loop->index) % 8]"/></span><b class="card-title" style="font-size:18px">{{ $item[0] ?? '' }}</b><span class="card-text">{{ site_md($item[1] ?? '') }}</span></div>
                @endforeach
            </div>
        </div>
    </section>
@endforeach

<section class="section white">
    <div class="wrap stack measure">
        <h2 style="font-size:clamp(28px,3vw,36px)">{{ site_md($c['report']['title']) }}</h2>
        <div class="prose"><p>{{ site_md($c['report']['text']) }}</p></div>
    </div>
</section>
@endsection
