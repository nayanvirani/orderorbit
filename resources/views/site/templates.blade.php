@extends('layouts.site')

@section('title', 'Shopify CRO Templates | OrderOrbit')
@section('description', 'Preview every OrderOrbit template: bundles, free gifts, shipping bars, upsells, countdowns, trust, checkout, Thank You, customer account and automation.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>{{ count($templates) }} templates</span>
        <h1>Start from a <span class="grad-text">proven template.</span></h1>
        <p class="lead">Preview every OrderOrbit template. Install the app to customise and publish.</p>
    </div>
</section>

<section class="section tight" style="padding-top:0">
    <div class="wrap" data-gallery>
        <div class="filter-label">Surface</div>
        <div class="filters" role="toolbar" aria-label="Filter by surface">
            <button class="tab" data-filter="surface" data-value="all">All surfaces</button>
            @foreach ($surfaces as $key => $label)
                <button class="tab" data-filter="surface" data-value="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="filter-label">Type</div>
        <div class="filters" role="toolbar" aria-label="Filter by type">
            <button class="tab" data-filter="type" data-value="all">All types</button>
            @foreach ($types as $key => $label)
                <button class="tab" data-filter="type" data-value="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="tpl-grid">
            @foreach ($templates as $tpl)
                <button type="button" class="tpl" data-surface="{{ $tpl['surface'] }}" data-type="{{ $tpl['type'] }}" data-feature="{{ $tpl['feature'] }}" data-name="{{ $tpl['name'] }}" data-label="{{ $tpl['label'] }} · {{ $surfaces[$tpl['surface']] }}">
                    <div class="thumb"><div style="filter:hue-rotate({{ [0, 28, -24, 52, -46][$loop->index % 5] }}deg)">@include('site.partials.thumb', ['type' => $tpl['type'], 'v' => $loop->index])</div></div>
                    <div class="meta"><b>{{ $tpl['name'] }}</b><span><span class="pill">{{ $tpl['label'] }}</span>@if ($surfaces[$tpl['surface']] !== $tpl['label'])<span class="pill gray">{{ $surfaces[$tpl['surface']] }}</span>@endif</span></div>
                </button>
            @endforeach
        </div>
        <div class="card center" data-empty hidden style="margin-top:20px">
            <h3>No templates match those filters.</h3>
            <button class="btn" style="margin-top:16px" data-clear>Clear filters</button>
        </div>
    </div>
</section>

<div class="modal" data-modal role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="box">
        <div class="box-head">
            <div><h3 id="modal-title" data-modal-title></h3><div class="muted" data-modal-sub></div></div>
            <div style="display:flex;gap:10px;align-items:center">
                <div class="seg" role="group" aria-label="Preview device">
                    <button type="button" data-device="desktop" aria-pressed="true"><x-icon name="monitor" style="width:15px;height:15px;display:inline-block;vertical-align:-3px"/> Desktop</button>
                    <button type="button" data-device="mobile" aria-pressed="false"><x-icon name="smartphone" style="width:15px;height:15px;display:inline-block;vertical-align:-3px"/> Mobile</button>
                </div>
                <button class="close" type="button" aria-label="Close" data-modal-close><x-icon name="x"/></button>
            </div>
        </div>
        <div class="frame" data-frame>
            @foreach (array_unique(array_column($templates, 'feature')) as $feature)
                <div data-preview="{{ $feature }}" hidden>@include('site.visuals.'.$feature)</div>
            @endforeach
        </div>
        <div class="ctas center" style="margin-top:28px">
            <a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install to use this template</a>
            <a class="btn lg" href="#" data-modal-link>About this feature</a>
        </div>
    </div>
</div>

@include('site.partials.cta')
@endsection
