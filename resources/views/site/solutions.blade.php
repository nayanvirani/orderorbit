@extends('layouts.site')

@section('title', 'Solutions | OrderOrbit Space')
@section('description', 'OrderOrbit Space setups for DTC brands, repeat-purchase brands, fashion & apparel and Shopify Plus.')

@section('content')
<section class="page-hero sky">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Solutions</span>
        <h1>Growth tools shaped for <span class="grad-text">your kind of store.</span></h1>
        <p class="lead">Pick your model to see the OrderOrbit Space setup that fits.</p>
    </div>
</section>
<section class="section tight" style="padding-top:0">
    <div class="wrap grid two">
        @foreach ($solutions as $slug => $s)
            <a class="card reveal" href="{{ route('site.solution', $slug) }}" style="padding:0;overflow:hidden">
                <div style="padding:28px 28px 0">
                    <div class="icon-badge"><x-icon :name="$s['icon']"/></div>
                    <h3 style="font-size:24px">{{ $s['name'] }}</h3>
                    <p style="margin-top:8px">{{ $s['hero'] }}</p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:16px">
                        @foreach ($s['setup'] as $item)<span class="pill">{{ $item }}</span>@endforeach
                    </div>
                    <span class="more">See the setup <x-icon name="arrow"/></span>
                </div>
                <div style="padding:28px;background:var(--tint);margin-top:24px">@include('site.diagrams.flow', ['steps' => $s['example']['flow']])</div>
            </a>
        @endforeach
    </div>
</section>
@include('site.partials.cta')
@endsection
