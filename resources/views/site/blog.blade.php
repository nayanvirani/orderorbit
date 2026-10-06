@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('blog'))
@php($categories = collect($posts)->pluck('category')->filter()->unique()->values())
@php($featured = $posts[0] ?? null)

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">{{ $c['eyebrow'] }}</span>
        <h1>{{ site_md($c['title']) }}</h1>
        <p class="lead">{{ site_md($c['lead']) }}</p>
        <div class="chips" role="group" aria-label="{{ $c['filter_label'] }}" data-chips="post-grid">
            <button type="button" class="chip" data-chip="all" aria-pressed="true">{{ $c['all'] }}</button>
            @foreach ($categories as $cat)<button type="button" class="chip" data-chip="{{ \Illuminate\Support\Str::slug($cat) }}" aria-pressed="false">{{ $cat }}</button>@endforeach
        </div>
    </div>
</section>

<section class="section" style="padding-top:64px">
    <div class="wrap grid" id="post-grid" style="--min:380px">
        @foreach ($posts as $post)
            @php($href = ! empty($post['feature']) ? route('site.feature', $post['feature']) : route('site.features'))
            <a class="card reveal" href="{{ $href }}" data-chip-item data-tags="{{ \Illuminate\Support\Str::slug($post['category'] ?? '') }}" @if ($loop->first) style="grid-column:1/-1;padding:40px" @endif>
                <span class="tags"><span class="badge soft">{{ $post['category'] ?? '' }}</span>@if (! empty($post['badge']))<span class="badge warn">{{ $post['badge'] }}</span>@endif</span>
                <span class="card-title" style="font-size:{{ $loop->first ? '32px' : '20px' }};line-height:1.2">{{ $post['title'] }}</span>
                <span class="card-text" style="font-size:{{ $loop->first ? '17px' : '15.5px' }}">{{ site_md($post['excerpt'] ?? '') }}</span>
                @if (! empty($post['feature']))<span class="card-link">{{ $c['related_feature'] }}</span>@endif
            </a>
        @endforeach
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta']])
@endsection
