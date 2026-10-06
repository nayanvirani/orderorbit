@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('templates'))
@php($features = \App\Support\Content::features())
@php($list = array_values(array_filter($templates, fn ($t) => isset($features[$t['feature']]))))
@php($used = collect($list)->pluck('feature')->unique()->values())
@php($hiddenTotal = array_sum(array_intersect_key($hidden, $features)))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}?v={{ @filemtime(base_path('extensions/orderorbit-theme/assets/orderorbit.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/checkout-preview.css') }}?v={{ filemtime(public_path('css/checkout-preview.css')) }}">
    <script>window.OO_SAMPLE_IMAGE = @json(\App\Services\Experiences\TemplateLibrary::samples()[0]['image']); window.OO_PREVIEW_JS = @json(asset('js/checkout-preview.js') . '?v=' . filemtime(public_path('js/checkout-preview.js')));</script>
@endpush

@section('content')
<section class="page-hero glow-hero">
    <div class="wrap">
        <span class="eyebrow">{{ site_md($c['eyebrow']) }}</span>
        <h1>{{ site_md($c['title']) }}</h1>
        <p class="lead">{{ site_md($c['lead']) }}</p>
    </div>
</section>

<section class="section" style="padding-top:48px">
    <div class="wrap stack lg">
        <div class="row between" style="align-items:center">
            <div class="chips" role="group" aria-label="{{ $c['filter_label'] }}" data-chips="tpl-grid">
                <button type="button" class="chip" data-chip="all" aria-pressed="true">{{ $c['all'] }}</button>
                @foreach ($used as $slug)<button type="button" class="chip" data-chip="{{ $slug }}" aria-pressed="false">{{ $features[$slug]['name'] }}</button>@endforeach
            </div>
            <span class="small dim" data-chip-count data-template="{{ \App\Support\SiteContent::plain($c['count']) }}">{{ \App\Support\SiteContent::plain($c['count'], ['count' => count($list), 'total' => $total]) }}</span>
        </div>
        <div class="grid" id="tpl-grid" style="--min:330px" data-template-gallery="{{ route('site.templates.previews') }}">
            @foreach ($list as $t)
                <div class="card tpl-card" data-chip-item data-tags="{{ $t['feature'] }}">
                    <div class="thumb live"><div class="oo-preview" data-tpl="{{ $t['type'] }}:{{ $t['key'] }}"><span class="tpl-loading" aria-hidden="true"></span></div></div>
                    <div class="body">
                        <span class="feature">{{ $features[$t['feature']]['name'] }}@if ($t['label'] !== $features[$t['feature']]['name']) · {{ $t['label'] }}@endif</span>
                        <span class="name">{{ $t['name'] }}</span>
                        <div class="actions">
                            <a class="btn secondary sm" href="{{ route('site.feature', $t['feature']) }}">{{ $c['preview'] }}</a>
                            <a class="btn ink sm" href="{{ config('shopify.install_url') }}" data-event="template_use_clicked">{{ $c['use'] }}</a>
                        </div>
                    </div>
                </div>
                @if (($hidden[$t['feature']] ?? 0) > 0 && ($list[$loop->index + 1]['feature'] ?? null) !== $t['feature'])
                    @php($vars = ['count' => $hidden[$t['feature']], 'feature' => $features[$t['feature']]['name']])
                    <a class="card tpl-card tpl-more" data-chip-item data-more="{{ $hidden[$t['feature']] }}" data-tags="{{ $t['feature'] }}" href="{{ config('shopify.install_url') }}" data-event="template_more_clicked">
                        <span class="tpl-more-stack" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span class="lock"><x-icon name="lock"/></span>
                        <b>{{ \App\Support\SiteContent::plain($c['more']['title'], $vars) }}</b>
                        <span>{{ site_md($c['more']['text'], $vars) }}</span>
                        <span class="btn ink sm">{{ $c['more']['button'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
        @if ($hiddenTotal > 0)
            <div class="tpl-locked">
                <div class="stack" style="gap:8px">
                    <h2>{{ site_md($c['locked']['title'], ['hidden' => $hiddenTotal, 'total' => $total]) }}</h2>
                    <p>{{ site_md($c['locked']['text'], ['hidden' => $hiddenTotal, 'total' => $total]) }}</p>
                </div>
                <a class="btn primary" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">{{ $c['locked']['button'] }}</a>
            </div>
        @endif
    </div>
</section>

<section class="section white">
    <div class="wrap stack xl">
        <h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($c['how']['title']) }}</h2>
        <div class="grid">
            @foreach ($c['how']['steps'] as $step)
                <div class="card soft"><span class="step-num">{{ $loop->iteration }}</span><b class="card-title" style="font-size:19px">{{ $step[0] ?? '' }}</b><span class="card-text">{{ site_md($step[1] ?? '') }}</span></div>
            @endforeach
        </div>
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta'], 'class' => ''])
@endsection

@push('scripts')
    <script src="{{ asset('js/site-templates.js') }}?v={{ filemtime(public_path('js/site-templates.js')) }}" defer></script>
@endpush
