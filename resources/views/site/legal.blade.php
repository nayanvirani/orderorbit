@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('legal'))

@section('title', $page->title.' | '.(\App\Support\Legal::details()['trading_name'] ?: 'OrderOrbit Space'))
@section('description', strip_tags((string) \App\Support\Legal::render((string) $page->summary)['html']) ?: $page->title)

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}">
@endpush

@section('content')
<section class="page-hero" style="padding-bottom:48px">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.legal.index') }}">{{ $c['eyebrow'] }}</a><span aria-hidden="true">/</span><span>{{ $page->title }}</span></nav>
        <h1 style="font-size:clamp(36px,4.4vw,52px)">{{ $page->title }}</h1>
        <div class="tags"><span class="badge soft">{{ $c['effective'] }} {{ $effective ?? '—' }}</span><span class="badge soft">{{ $c['version'] }} {{ $page->version }}</span></div>
    </div>
</section>

<div class="docs-layout wrap">
    <aside class="docs-toc" aria-label="{{ $c['policies'] }}">
        <p class="docs-rail-title">{{ $c['policies'] }}</p>
        <ul class="docs-all">@foreach ($pages as $p)<li><a href="{{ \App\Support\Legal::url($p['slug']) }}" @if ($p['slug'] === $page->slug) aria-current="page" @endif>{{ $p['title'] }}</a></li>@endforeach</ul>
        @if (count($toc))
            <p class="docs-rail-title" style="margin-top:24px">{{ $c['on_this_page'] }}</p>
            <ol>@foreach ($toc as [$id, $text])<li><a href="#{{ $id }}" data-toc>{{ preg_replace('/^\d+\.\s*/', '', $text) }}</a></li>@endforeach</ol>
        @endif
    </aside>

    <article class="docs-body legal-body">
        {{ $html }}
    </article>
</div>
@endsection
