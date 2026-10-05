@extends('layouts.site')

@section('title', $page->title.' | '.(\App\Support\Legal::details()['trading_name'] ?: 'OrderOrbit Space'))
@section('description', strip_tags((string) \App\Support\Legal::render((string) $page->summary)['html']) ?: $page->title)

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}">
@endpush

@section('content')
<div class="mn docs">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker"><a href="{{ route('site.legal.index') }}">Legal</a> · {{ $page->title }}</span>
            <h1>{{ $page->title }}</h1>
            <p class="docs-meta">Effective {{ $effective ?? '—' }} · Version {{ $page->version }}</p>
        </div>
    </section>

    <div class="docs-layout wrap">
        <aside class="docs-toc" aria-label="On this page">
            @if (count($toc))
                <p class="mn-group">On this page</p>
                <ol>@foreach ($toc as [$id, $text])<li><a href="#{{ $id }}">{{ preg_replace('/^\d+\.\s*/', '', $text) }}</a></li>@endforeach</ol>
            @endif
            <p class="mn-group" style="margin-top:24px">Policies</p>
            <ul class="docs-all">@foreach ($pages as $p)<li><a href="{{ \App\Support\Legal::url($p['slug']) }}" @if ($p['slug'] === $page->slug) aria-current="page" @endif>{{ $p['title'] }}</a></li>@endforeach</ul>
        </aside>

        <article class="docs-body legal-body">
            {{ $html }}
        </article>
    </div>
</div>
@endsection
