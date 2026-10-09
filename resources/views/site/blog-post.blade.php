@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('blog'))
@php($feature = $post->feature ? \App\Support\Content::feature($post->feature) : null)

@section('title', ($post->seo_title ?: $post->title).' | Growvia')
@section('description', $post->seo_description ?: $post->excerpt)

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}">
    @if ($preview)<meta name="robots" content="noindex, nofollow">@endif
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post->title, 'description' => $post->seo_description ?: $post->excerpt, 'datePublished' => $post->published_at?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(), 'author' => ['@type' => 'Organization', 'name' => 'Growvia'], 'mainEntityOfPage' => $post->exists ? $post->url() : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
@if ($preview)
    <div class="wrap" style="padding-top:16px"><p class="badge warn">Preview: this is how the article will look. It isn't public until you publish it.</p></div>
@endif
<section class="page-hero" style="padding-bottom:48px">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('site.blog') }}">{{ $c['eyebrow'] }}</a><span aria-hidden="true">/</span><span>{{ $post->category ?: $c['all_articles'] }}</span></nav>
        <h1 style="font-size:clamp(34px,4.2vw,52px);max-width:22ch">{{ $post->title }}</h1>
        @if ($post->excerpt)<p class="lead">{{ $post->excerpt }}</p>@endif
        <div class="tags">
            @if ($post->category)<span class="badge soft">{{ $post->category }}</span>@endif
            <span class="badge soft">{{ ($post->published_at ?? now())->format('F j, Y') }}</span>
            <span class="badge soft">{{ $post->readingMinutes() }} {{ $c['min_read'] }}</span>
        </div>
    </div>
</section>

<div class="docs-layout wrap">
    <aside class="docs-toc" aria-label="{{ $c['on_this_page'] }}">
        <p class="docs-rail-title"><a href="{{ route('site.blog') }}">← {{ $c['all_articles'] }}</a></p>
        @if (count($toc))
            <p class="docs-rail-title" style="margin-top:24px">{{ $c['on_this_page'] }}</p>
            <ol>@foreach ($toc as [$id, $text])<li><a href="#{{ $id }}" data-toc>{{ $text }}</a></li>@endforeach</ol>
        @endif
    </aside>

    <article class="docs-body legal-body">
        {{ $html }}

        @if ($feature)
            <aside class="card" style="margin-top:40px">
                <span class="eyebrow">{{ $c['feature_box'] }}</span>
                <span class="card-title" style="font-size:22px">{{ $feature['name'] ?? $feature['title'] ?? \Illuminate\Support\Str::headline($post->feature) }}</span>
                <a class="card-link" href="{{ route('site.feature', $post->feature) }}">{{ $c['feature_link'] }}</a>
            </aside>
        @endif
    </article>
</div>

@if ($more->isNotEmpty())
    <section class="section" style="padding-top:24px">
        <div class="wrap">
            <h2 style="margin-bottom:24px">{{ $c['more'] }}</h2>
            <div class="grid" style="--min:300px">
                @foreach ($more as $m)
                    <a class="card" href="{{ $m->url() }}">
                        <span class="tags">@if ($m->category)<span class="badge soft">{{ $m->category }}</span>@endif<span class="badge soft">{{ $m->readingMinutes() }} {{ $c['min_read'] }}</span></span>
                        <span class="card-title" style="font-size:19px;line-height:1.25">{{ $m->title }}</span>
                        <span class="card-text">{{ $m->excerpt }}</span>
                        <span class="card-link">{{ $c['read'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

@include('site.partials.cta', ['cta' => $c['cta']])
@endsection
