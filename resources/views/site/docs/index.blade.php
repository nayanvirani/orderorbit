@extends('layouts.site')

@section('title', 'Documentation | OrderOrbit Space')
@section('description', 'Guides for every part of OrderOrbit Space: getting started, bundles, gifts, widgets, checkout, customer accounts, automation, analytics, personalization, A/B testing and developer callbacks.')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}">
@endpush

@section('content')
<div class="mn docs">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Docs</span>
            <h1>Everything you need to <em>get results.</em></h1>
            <p class="mn-lead">Step-by-step guides for every part of OrderOrbit Space, from your first widget to A/B tests and automation.</p>
        </div>
    </section>
    <div class="wrap docs-hub">
        @foreach ($guides as $slug => $g)
            <a class="docs-card" href="{{ route('site.docs', $slug) }}">
                <span class="docs-card-ico"><x-icon :name="$g['icon']" /></span>
                <strong>{{ $g['title'] }}</strong>
                <span>{{ $g['summary'] }}</span>
                <em>Read the guide →</em>
            </a>
        @endforeach
    </div>
    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Can't find it?</h2>
            <p>Search the <a href="{{ route('site.help') }}">help center</a>, or open Support inside the app: it includes your store details automatically.</p>
        </div>
    </section>
</div>
@endsection
