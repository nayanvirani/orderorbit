@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('legal'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']).' | '.(\App\Support\Legal::details()['trading_name'] ?: 'OrderOrbit Space'))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
@include('site.partials.page-hero', ['c' => $c])

<section class="section" style="padding-top:72px">
    <div class="wrap grid">
        @foreach ($pages as $p)
            <a class="card reveal" href="{{ \App\Support\Legal::url($p['slug']) }}">
                <span class="card-title" style="font-size:19px">{{ $p['title'] }}</span>
                <span class="card-text">{{ strip_tags((string) \App\Support\Legal::render((string) $p['summary'])['html']) }}</span>
                <span class="small dim" style="margin-top:auto">{{ $c['effective'] }} {{ $p['effective_at'] ? \Illuminate\Support\Carbon::parse($p['effective_at'])->toFormattedDateString() : '—' }} · {{ $c['version'] }} {{ $p['version'] }}</span>
            </a>
        @endforeach
    </div>
</section>
@endsection
