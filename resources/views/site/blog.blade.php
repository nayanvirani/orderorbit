@extends('layouts.site')

@section('title', 'Blog | Shopify Conversion, Explained | OrderOrbit Space')
@section('description', 'Practical guides on bundles, gifts, upsells, checkout and retention for Shopify stores.')

@php($groups = collect($posts)->groupBy('category'))

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Blog</span>
            <h1>Shopify conversion, <em>explained.</em></h1>
            <p class="mn-lead">Practical, plain-English guides on raising order value and conversion. We're writing the first articles now — here's what's coming.</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-wide">
            @foreach ($groups as $category => $list)
                <p class="mn-group">{{ $category }}</p>
                <ul class="mn-rows">
                    @foreach ($list as $post)
                        <li>
                            @if ($post['feature'])
                                <a href="{{ route('site.feature', $post['feature']) }}"><b>{{ $post['title'] }}<span class="soon">Soon</span></b><span>{{ $post['excerpt'] }}</span><i>Related feature →</i></a>
                            @else
                                <a href="{{ route('site.features') }}"><b>{{ $post['title'] }}<span class="soon">Soon</span></b><span>{{ $post['excerpt'] }}</span><i>All features →</i></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Want a topic covered?</h2>
            <p>Tell us what you'd like to read about and we'll add it to the list.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ route('site.contact') }}">Suggest a topic</a><a class="btn lg" href="{{ route('site.help') }}">Help Center</a></div>
        </div>
    </section>
</div>
@endsection
