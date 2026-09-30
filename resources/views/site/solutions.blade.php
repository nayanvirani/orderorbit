@extends('layouts.site')

@section('title', 'Solutions | OrderOrbit Space')
@section('description', 'How DTC, repeat-purchase, fashion and Shopify Plus brands use OrderOrbit Space to raise order value.')

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Solutions</span>
            <h1>A setup for <em>your kind of store.</em></h1>
            <p class="mn-lead">Pick the model closest to yours to see which offers to start with and why.</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-wide">
            <ul class="mn-rows">
                @foreach ($solutions as $slug => $s)
                    <li><a href="{{ route('site.solution', $slug) }}"><b>{{ $s['name'] }}</b><span>{{ $s['hero'] }}</span><i>See the setup →</i></a></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Not sure where to start?</h2>
            <p>Most stores begin with a quantity-break bundle on their best-selling product and a free-shipping bar.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a><a class="btn lg" href="{{ route('site.contact') }}">Ask us</a></div>
        </div>
    </section>
</div>
@endsection
