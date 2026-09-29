@extends('layouts.site')

@section('title', 'Resources | OrderOrbit')
@section('description', 'Guides, templates and answers for growing conversion, order value and repeat purchase on Shopify.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Resources</span>
        <h1>Resources for better <span class="grad-text">Shopify conversion.</span></h1>
        <p class="lead">Guides, templates and answers for growing conversion, order value and repeat purchase.</p>
    </div>
</section>

<section class="section tight" style="padding-top:0">
    <div class="wrap">
        <div class="example-card reveal" style="grid-template-columns:1fr">
            <div class="split" style="gap:32px">
                <div class="split-copy">
                    <span class="label">Featured guide</span>
                    <h3 style="font-size:clamp(24px,3vw,34px)">{{ $posts[0]['title'] }}</h3>
                    <p>{{ $posts[0]['excerpt'] }}</p>
                    <span class="draft"><x-icon name="clock"/>Publishing soon</span>
                </div>
                <div style="max-width:460px;width:100%;margin:0 auto">@include('site.diagrams.orbit', ['light' => true])</div>
            </div>
        </div>
    </div>
</section>

<section class="section tight">
    <div class="wrap">
        <div class="section-head left reveal" style="margin-bottom:28px"><h2 style="font-size:30px">Latest from the blog</h2></div>
        <div class="grid three">
            @foreach (array_slice($posts, 1, 3) as $post)
                @include('site.partials.post-card', ['post' => $post, 'i' => $loop->index + 1])
            @endforeach
        </div>
        <div class="ctas" style="margin-top:28px"><a class="btn" href="{{ route('site.blog') }}">View all posts <x-icon name="arrow"/></a></div>
    </div>
</section>

<section class="section tint">
    <div class="wrap split">
        <div class="split-copy reveal">
            <span class="eyebrow"><span class="dot"></span>Template gallery</span>
            <h2>{{ $templateCount }} templates to start from.</h2>
            <p class="lead">Bundles, gifts, shipping bars, upsells, countdowns, trust, checkout, Thank You, customer account and automation.</p>
            <a class="btn dark" href="{{ route('site.templates') }}">Browse Templates <x-icon name="arrow"/></a>
        </div>
        <div class="tpl-grid reveal thumb-pair">
            @foreach (['bundle', 'shipping', 'qty', 'thankyou'] as $type)
                <div class="tpl"><div class="thumb"><div>@include('site.partials.thumb', ['type' => $type, 'v' => $loop->index])</div></div></div>
            @endforeach
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head reveal"><span class="eyebrow"><span class="dot"></span>Help center</span><h2>Browse by topic.</h2></div>
        <div class="grid four">
            @foreach (array_slice($helpCategories, 0, 8) as $c)
                <a class="card reveal" href="{{ route('site.help') }}#{{ $c['slug'] }}"><div class="icon-badge soft"><x-icon :name="$c['icon']"/></div><h3 style="font-size:17px">{{ $c['name'] }}</h3><p>{{ $c['text'] }}</p></a>
            @endforeach
        </div>
        <div class="card center reveal" style="margin-top:28px">
            <h3>Can't find it? Contact us.</h3>
            <p>We reply within one business day.</p>
            <div class="ctas center" style="margin-top:16px"><a class="btn dark" href="{{ route('site.contact') }}">Contact us</a></div>
        </div>
    </div>
</section>
@endsection
