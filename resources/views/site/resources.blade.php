@extends('layouts.site')

@section('title', 'Resources | OrderOrbit Space')
@section('description', 'Guides, templates and help for raising conversion and order value on Shopify with OrderOrbit Space.')

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Resources</span>
            <h1>Everything you need <em>to get started.</em></h1>
            <p class="mn-lead">Help articles, the template gallery and upcoming guides — all in one place.</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-wide">
            <ul class="mn-rows">
                <li><a href="{{ route('site.help') }}"><b>Help Center</b><span>Answers on setup, templates, bundles, gifts, analytics, billing and troubleshooting.</span><i>Open →</i></a></li>
                <li><a href="{{ route('site.templates') }}"><b>Template gallery</b><span>The ready-made layouts for every live feature, each one fully customisable.</span><i>Browse →</i></a></li>
                <li><a href="{{ route('site.how') }}"><b>How it works</b><span>Install, pick a template, make it yours, publish and measure — in five steps.</span><i>Read →</i></a></li>
                <li><a href="{{ route('site.blog') }}"><b>Blog</b><span>Practical guides on bundles, gifts, upsells and retention. First articles coming soon.</span><i>See topics →</i></a></li>
            </ul>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>Popular questions</h2>
            @include('site.partials.faq', ['faqs' => collect($helpCategories)->flatMap(fn ($c) => array_slice($c['articles'], 0, 1))->take(6)->all()])
            <p style="margin:22px 0 0"><a class="btn" href="{{ route('site.help') }}">All help topics</a></p>
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Can't find what you need?</h2>
            <p>We reply within one business day.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ route('site.contact') }}">Contact us</a></div>
        </div>
    </section>
</div>
@endsection
