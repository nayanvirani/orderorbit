@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('home'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'OrderOrbit Space',
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Shopify',
    'description' => \App\Support\SiteContent::plain($c['seo_description']),
    'offers' => array_values(array_map(fn ($p) => ['@type' => 'Offer', 'name' => $p['name'], 'price' => $p['price'], 'priceCurrency' => 'USD'], $plans)),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@php($hero = $c['hero'])
@php($mock = $hero['mockup'])
@php($cards = array_slice($groups['convert'], 0, 8, true))

@section('content')
<section class="home-hero">
    <div class="wrap split">
        <div class="stack lg">
            <span class="eyebrow dot">{{ site_md($hero['eyebrow']) }}</span>
            <h1>{{ site_md($hero['title']) }}</h1>
            <p class="lead">{{ site_md($hero['lead']) }}</p>
            <div class="row">
                <a class="btn primary" href="{{ site_url($hero['cta_primary']['href']) }}" data-event="cta_install_clicked">{{ $hero['cta_primary']['label'] }}</a>
                <a class="btn secondary" href="{{ site_url($hero['cta_secondary']['href']) }}">{{ $hero['cta_secondary']['label'] }}</a>
            </div>
            <ul class="checks">@foreach ($hero['checks'] as $check)<li><x-icon name="check"/>{{ $check }}</li>@endforeach</ul>
        </div>
        <div class="mock" aria-hidden="true">
            <div class="browser">
                <div class="browser-bar"><i></i><i></i><i></i><span>{{ $mock['url'] }}</span></div>
                <div class="browser-body">
                    <div class="product-art"><svg viewBox="0 0 90 150"><rect x="30" y="4" width="30" height="26" rx="4" fill="var(--c-heading)"/><rect x="8" y="30" width="74" height="116" rx="16" fill="var(--c-surface)" stroke="var(--c-line-strong)"/><rect x="22" y="70" width="46" height="40" rx="6" fill="currentColor"/></svg></div>
                    <div class="product-info">
                        <strong>{{ $mock['product'] }}</strong>
                        <span>{{ $mock['price'] }}</span>
                        <span class="bundle-label">{{ $mock['bundle_label'] }}</span>
                        @foreach ($mock['tiers'] as $tier)
                            <div class="tier {{ $loop->index === 1 ? 'on' : '' }}">
                                @if ($loop->index === 1)<span class="ribbon">{{ $mock['popular'] }}</span>@endif
                                <span class="radio"></span>
                                <span class="body"><b>{{ $tier['title'] }}</b> @if ($tier['badge'])<span class="off">{{ $tier['badge'] }}</span>@endif<small>{{ $tier['note'] }}</small></span>
                                <b>{{ $tier['price'] }}</b>
                            </div>
                        @endforeach
                        <span class="mock-button">{{ $mock['button'] }}</span>
                    </div>
                </div>
            </div>
            <div class="mini-cards">
                <div class="mini"><strong>{{ $mock['gift_text'] }}</strong><span class="meter"><span style="width:68%"></span></span><span class="ends"><span>{{ $mock['gift_left'] }}</span><span>{{ $mock['gift_right'] }}</span></span></div>
                <div class="mini timer"><strong>{{ $mock['timer_label'] }}</strong><span class="timer-tiles" data-countdown><b>02</b><b>14</b><b>09</b></span></div>
            </div>
        </div>
    </div>
</section>

<section class="surfaces">
    <div class="wrap"><b>{{ $c['surfaces']['title'] }}</b>@foreach ($c['surfaces']['items'] as $item)<span>{{ $item }}</span>@endforeach</div>
</section>

@php($s = $c['stack'])
<section class="section">
    <div class="wrap stack xl">
        <div class="row between">
            <div class="stack measure"><span class="kicker">{{ $s['eyebrow'] }}</span><h2>{{ site_md($s['title']) }}</h2><p class="lead" style="font-size:18px">{{ site_md($s['text']) }}</p></div>
            @if ($s['link']['label'])<a class="link" href="{{ site_url($s['link']['href']) }}">{{ $s['link']['label'] }}</a>@endif
        </div>
        <div class="stack-cmp reveal">
            <div class="card">
                <span class="label-dim">{{ $s['before'] }}</span>
                <div class="apps">@foreach ($s['apps'] as $app)<span>{{ $app[0] ?? '' }}<b>{{ $app[1] ?? '' }}</b></span>@endforeach</div>
            </div>
            <div class="after">
                <span class="label">{{ $s['after'] }}</span>
                <span class="price">{{ site_md($s['price']) }} <small>{{ site_md($s['price_note']) }}</small></span>
                <div class="stats">@foreach ($s['stats'] as $stat)<div><span>{{ $stat[0] ?? '' }}</span><b>{{ site_md($stat[1] ?? '') }}</b></div>@endforeach</div>
                <p class="fine">{{ site_md($s['fine']) }}</p>
            </div>
        </div>
    </div>
</section>

@php($f = $c['features'])
<section class="section white">
    <div class="wrap stack xl">
        <div class="stack measure"><span class="kicker">{{ $f['eyebrow'] }}</span><h2>{{ site_md($f['title']) }}</h2><p class="lead" style="font-size:18px">{{ site_md($f['text']) }}</p></div>
        <div class="grid">
            @foreach ($cards as $slug => $feature)
                <a class="card feature-card reveal" href="{{ route('site.feature', $slug) }}">
                    @include('site.partials.peek', ['slug' => $slug])
                    <div class="body"><span class="card-title">{{ $feature['name'] }}</span><span class="card-text">{{ $feature['summary'] ?? $feature['menu'] }}</span></div>
                </a>
            @endforeach
            <a class="card dashed wide-row reveal" href="{{ site_url($f['more_link']['href']) }}">
                <span class="card-title">{{ site_md($f['more_title']) }}</span>
                <span class="card-text">{{ site_md($f['more_text']) }}</span>
                <span class="card-link">{{ $f['more_link']['label'] }}</span>
            </a>
        </div>
    </div>
</section>

@php($d = $c['day_one'])
<section class="section">
    <div class="wrap stack xl">
        <div class="stack measure"><span class="kicker">{{ $d['eyebrow'] }}</span><h2>{{ site_md($d['title']) }}</h2></div>
        <div class="cells reveal">@foreach ($d['cards'] as $card)<div><b class="card-title">{{ $card[0] ?? '' }}</b><span class="card-text">{{ site_md($card[1] ?? '') }}</span></div>@endforeach</div>
    </div>
</section>

@php($a = $c['analytics'])
<section class="section dark">
    <div class="wrap split">
        <div class="stack lg">
            <span class="kicker">{{ $a['eyebrow'] }}</span>
            <h2>{{ site_md($a['title']) }}</h2>
            <p class="lead" style="font-size:18px">{{ site_md($a['text']) }}</p>
            @if ($a['link']['label'])<a class="link" href="{{ site_url($a['link']['href']) }}" style="color:var(--heading)">{{ $a['link']['label'] }}</a>@endif
        </div>
        <div class="kpi-panel reveal">
            <div class="row between" style="align-items:center"><b style="color:var(--heading)">{{ $a['panel_title'] }}</b><span class="small dim">{{ $a['panel_note'] }}</span></div>
            <div class="kpis">@foreach ($a['stats'] as $stat)<div><span>{{ $stat[0] ?? '' }}</span><b>{{ $stat[1] ?? '' }}</b><em>{{ $stat[2] ?? '' }}</em></div>@endforeach</div>
            <div class="bars" aria-hidden="true">@foreach ([38, 52, 46, 64, 58, 72, 68, 84, 78, 96] as $h)<i class="{{ $loop->index > 6 ? 'on' : '' }}" style="height:{{ $h }}%"></i>@endforeach</div>
        </div>
    </div>
</section>

@php($h = $c['how'])
<section class="section">
    <div class="wrap stack xl">
        <div class="stack measure"><span class="kicker">{{ $h['eyebrow'] }}</span><h2>{{ site_md($h['title']) }}</h2></div>
        <div class="grid" style="--min:240px">
            @foreach ($h['steps'] as $step)
                <div class="card reveal"><span class="step-num">{{ $loop->iteration }}</span><b class="card-title" style="font-size:19px">{{ $step[0] ?? '' }}</b><span class="card-text">{{ site_md($step[1] ?? '') }}</span></div>
            @endforeach
        </div>
    </div>
</section>

@php($pr = $c['pricing'])
<section class="section white">
    <div class="wrap stack xl">
        <div class="row between">
            <div class="stack"><span class="kicker">{{ $pr['eyebrow'] }}</span><h2>{{ site_md($pr['title']) }}</h2></div>
            @if ($pr['link']['label'])<a class="link" href="{{ site_url($pr['link']['href']) }}">{{ $pr['link']['label'] }}</a>@endif
        </div>
        <div class="grid" style="--min:250px">
            @foreach ($plans as $plan)
                <div class="plan-mini {{ ! empty($plan['badge']) ? 'featured' : '' }} reveal">
                    <span class="head">{{ $plan['name'] }}@if (! empty($plan['badge']))<span class="badge">{{ $plan['badge'] }}</span>@endif</span>
                    <span class="price">@if ($plan['price'] > 0)${{ number_format($plan['price'], 2) }}<small>{{ $pr['per_month'] }}</small>@else{{ $pr['free'] }}@endif</span>
                    <span class="card-text">{{ $plan['description'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>

@php($q = $c['faq'])
<section class="section">
    <div class="wrap split top">
        <div class="narrow stack"><span class="kicker">{{ $q['eyebrow'] }}</span><h2>{{ site_md($q['title']) }}</h2><p class="lead" style="font-size:18px">{{ site_md($q['text']) }}</p></div>
        <div class="wide">@include('site.partials.faq', ['faqs' => array_map(fn ($f) => [\App\Support\SiteContent::fill($f[0] ?? ''), \App\Support\SiteContent::fill($f[1] ?? '')], $q['faqs'])])</div>
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta']])
@endsection
