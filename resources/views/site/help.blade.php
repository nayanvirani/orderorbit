@extends('layouts.site')

@section('title', 'Help Center | OrderOrbit Space')
@section('description', 'Answers about setting up bundles, progressive gifts, upsells, countdowns, analytics and billing in OrderOrbit Space.')

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Help center</span>
            <h1>How can we <em>help?</em></h1>
            <p class="mn-lead">Short answers to the questions merchants ask most, from installing the app to reading your analytics.</p>
            <label class="search"><x-icon name="search"/><input type="search" placeholder="Search help topics…" aria-label="Search help topics" data-help-search></label>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-narrow">
            <p class="mn-group">Topics</p>
            <ul class="mn-chips">
                @foreach ($helpCategories as $c)<li><a href="#{{ $c['slug'] }}">{{ $c['name'] }}</a></li>@endforeach
            </ul>
        </div>
    </section>

    @foreach ($helpCategories as $c)
        <section class="mn-section" id="{{ $c['slug'] }}" data-help-item>
            <div class="mn-narrow">
                <h2>{{ $c['name'] }}</h2>
                <p class="mn-intro">{{ $c['text'] }}</p>
                @include('site.partials.faq', ['faqs' => $c['articles'], 'openFirst' => false])
            </div>
        </section>
    @endforeach

    <section class="mn-section" data-help-empty hidden>
        <div class="mn-narrow">
            <h2>Nothing found for "<span data-help-q></span>"</h2>
            <p class="mn-intro">Try different words or <a href="{{ route('site.contact') }}">contact us</a>.</p>
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Still stuck?</h2>
            <p>Open Support inside the app — it includes your store details automatically — or send us a message. We reply within one business day.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ route('site.contact') }}">Contact us</a></div>
        </div>
    </section>
</div>
@endsection
