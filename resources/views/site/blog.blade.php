@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('blog'))
@php($categories = $posts->pluck('category')->filter()->unique()->values())

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">{{ $c['eyebrow'] }}</span>
        <h1>{{ site_md($c['title']) }}</h1>
        <p class="lead">{{ site_md($c['lead']) }}</p>
        @if ($categories->count() > 1)
            <div class="chips" role="group" aria-label="{{ $c['filter_label'] }}" data-chips="post-grid">
                <button type="button" class="chip" data-chip="all" aria-pressed="true">{{ $c['all'] }}</button>
                @foreach ($categories as $cat)<button type="button" class="chip" data-chip="{{ \Illuminate\Support\Str::slug($cat) }}" aria-pressed="false">{{ $cat }}</button>@endforeach
            </div>
        @endif
    </div>
</section>

<section class="section" style="padding-top:64px">
    <div class="wrap grid" id="post-grid" style="--min:380px">
        @forelse ($posts as $post)
            <a class="card reveal" href="{{ $post->url() }}" data-chip-item data-tags="{{ \Illuminate\Support\Str::slug($post->category ?? '') }}" @if ($loop->first) style="grid-column:1/-1;padding:40px" @endif>
                <span class="tags">@if ($post->category)<span class="badge soft">{{ $post->category }}</span>@endif<span class="badge soft">{{ $post->published_at->format('M j, Y') }} · {{ $post->readingMinutes() }} {{ $c['min_read'] }}</span></span>
                <span class="card-title" style="font-size:{{ $loop->first ? '32px' : '20px' }};line-height:1.2">{{ $post->title }}</span>
                <span class="card-text" style="font-size:{{ $loop->first ? '17px' : '15.5px' }}">{{ $post->excerpt }}</span>
                <span class="card-link">{{ $c['read'] }}</span>
            </a>
        @empty
            <p class="lead">{{ $c['empty'] }}</p>
        @endforelse
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta']])
@endsection
