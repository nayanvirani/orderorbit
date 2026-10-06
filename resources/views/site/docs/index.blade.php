@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('docs'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
@include('site.partials.page-hero', ['c' => $c])

<section class="section" style="padding-top:72px">
    <div class="wrap grid">
        @foreach ($guides as $slug => $g)
            <a class="card reveal" href="{{ route('site.docs', $slug) }}"><span class="icon-tile"><x-icon :name="$g['icon'] ?? 'book'"/></span><span class="card-title" style="font-size:19px">{{ $g['title'] }}</span><span class="card-text">{{ site_md($g['summary']) }}</span><span class="card-link">{{ $c['read'] }}</span></a>
        @endforeach
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta']])
@endsection
