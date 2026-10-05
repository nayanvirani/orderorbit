@extends('layouts.site')

@section('title', 'Legal & policies | '.(\App\Support\Legal::details()['trading_name'] ?: 'OrderOrbit Space'))
@section('description', 'Terms of Service, Privacy Policy, Data Processing Addendum, billing and refunds, acceptable use, cookies, subprocessors and support.')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}">
@endpush

@section('content')
<div class="mn docs">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Legal</span>
            <h1>Legal &amp; policies</h1>
            <p class="mn-lead">The terms that apply when you use {{ \App\Support\Legal::details()['trading_name'] ?: 'OrderOrbit Space' }}, and how we handle data.</p>
        </div>
    </section>
    <div class="wrap legal-index">
        @foreach ($pages as $p)
            <a class="legal-card" href="{{ \App\Support\Legal::url($p['slug']) }}">
                <b>{{ $p['title'] }}</b>
                <span>{{ strip_tags((string) \App\Support\Legal::render((string) $p['summary'])['html']) }}</span>
                <small>Effective {{ $p['effective_at'] ? \Illuminate\Support\Carbon::parse($p['effective_at'])->toFormattedDateString() : '—' }} · Version {{ $p['version'] }}</small>
            </a>
        @endforeach
    </div>
</div>
@endsection
