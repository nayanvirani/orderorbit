@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('templates'))
@php($features = \App\Support\Content::features())
@php($list = array_values(array_filter($templates, fn ($t) => isset($features[$t['feature']]))))
@php($used = collect($list)->pluck('feature')->unique()->values())

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', \App\Support\SiteContent::plain($c['seo_description']))

@section('content')
@include('site.partials.page-hero', ['c' => $c])

<section class="section" style="padding-top:56px">
    <div class="wrap stack lg">
        <div class="row between" style="align-items:center">
            <div class="chips" role="group" aria-label="{{ $c['filter_label'] }}" data-chips="tpl-grid">
                <button type="button" class="chip" data-chip="all" aria-pressed="true">{{ $c['all'] }}</button>
                @foreach ($used as $slug)<button type="button" class="chip" data-chip="{{ $slug }}" aria-pressed="false">{{ $features[$slug]['name'] }}</button>@endforeach
            </div>
            <span class="small dim" data-chip-count data-template="{{ \App\Support\SiteContent::plain($c['count']) }}">{{ \App\Support\SiteContent::plain($c['count'], ['count' => count($list)]) }}</span>
        </div>
        <div class="grid" id="tpl-grid">
            @foreach ($list as $t)
                <div class="card tpl-card" data-chip-item data-tags="{{ $t['feature'] }}">
                    <div class="thumb" aria-hidden="true">@include('site.partials.thumb', ['type' => $t['type'], 'v' => $loop->index % 3])</div>
                    <div class="body">
                        <span class="feature">{{ $features[$t['feature']]['name'] }}</span>
                        <span class="name">{{ $t['name'] }}</span>
                        <div class="actions">
                            <a class="btn secondary sm" href="{{ route('site.feature', $t['feature']) }}">{{ $c['preview'] }}</a>
                            <a class="btn ink sm" href="{{ config('shopify.install_url') }}" data-event="template_use_clicked">{{ $c['use'] }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
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
