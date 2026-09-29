@extends('layouts.site')

@section('title', 'Blog | Shopify Conversion, Explained | OrderOrbit')
@section('description', 'Practical guides on CRO, bundles, upsells, checkout, retention and testing for Shopify stores.')

@section('content')
<section class="page-hero sky">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Blog</span>
        <h1>Shopify conversion, <span class="grad-text">explained.</span></h1>
        <p class="lead">Practical guides on CRO, bundles, upsells, checkout, retention and testing.</p>
    </div>
</section>
<section class="section tight" style="padding-top:0">
    <div class="wrap" data-tabs>
        <div class="filters" style="justify-content:center">
            @foreach (['All', 'CRO', 'AOV', 'Checkout', 'Retention', 'Testing', 'Analytics'] as $cat)
                <button class="tab" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="blog-{{ \Illuminate\Support\Str::slug($cat) }}">{{ $cat }}</button>
            @endforeach
        </div>
        @foreach (['All', 'CRO', 'AOV', 'Checkout', 'Retention', 'Testing', 'Analytics'] as $cat)
            @php($list = $cat === 'All' ? $posts : array_values(array_filter($posts, fn ($p) => $p['category'] === $cat)))
            <div role="tabpanel" id="blog-{{ \Illuminate\Support\Str::slug($cat) }}" @unless ($loop->first) hidden @endunless>
                @if ($list)
                    <div class="grid three">
                        @foreach ($list as $post)@include('site.partials.post-card', ['post' => $post, 'i' => $loop->index])@endforeach
                    </div>
                @else
                    <div class="card center"><h3>Posts in {{ $cat }} are on the way.</h3><p>Browse all posts in the meantime.</p></div>
                @endif
            </div>
        @endforeach
    </div>
</section>
@include('site.partials.cta')
@endsection
