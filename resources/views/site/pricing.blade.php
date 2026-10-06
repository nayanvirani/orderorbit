@extends('layouts.site')
@php($c = \App\Support\SiteContent::page('pricing'))

@section('title', \App\Support\SiteContent::plain($c['seo_title']))
@section('description', $page['lead'].' '.collect($plans)->map(fn ($p) => $p['name'].' '.($p['price'] > 0 ? '$'.number_format($p['price'], 2).'/mo' : 'free'))->implode(', ').'. '.$page['billing_note'])

@section('content')
<section class="page-hero center" style="border-bottom:0;padding-bottom:40px">
    <div class="wrap">
        <span class="eyebrow">{{ $c['eyebrow'] }}</span>
        <h1>{{ site_md($page['headline']) }}</h1>
        <p class="lead">{{ site_md($page['lead']) }}</p>
        <ul class="checks"><li><x-icon name="check"/>{{ $page['billing_note'] }}@if ($page['trial_note']) {{ $page['trial_note'] }}@endif</li></ul>
    </div>
</section>

<section class="section white" style="padding-top:24px;border-top:0">
    <div class="wrap">@include('site.partials.plan-cards', ['plans' => $plans])</div>
</section>

<section class="section">
    <div class="wrap stack lg">
        <h2 style="font-size:clamp(28px,3vw,40px)">{{ $page['compare_title'] }}</h2>
        <div class="table-wrap">
            <table class="compare">
                <thead><tr><th></th>@foreach ($plans as $key => $plan)<th class="{{ ! empty($plan['badge']) ? 'featured' : '' }}">{{ $plan['name'] }}<small>{{ $plan['price'] > 0 ? '$'.number_format($plan['price'], 2).$c['per_month'] : $c['compare_free'] }}</small></th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($compare as $group)
                        <tr class="group"><td colspan="{{ count($plans) + 1 }}">{{ $group['group'] }}</td></tr>
                        @foreach ($group['rows'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                @foreach ($plans as $key => $plan)
                                    @php($cell = $row['values'][$key] ?? false)
                                    <td class="{{ ! empty($plan['badge']) ? 'featured' : '' }}">@if ($cell === true)<x-icon name="check" class="yes"/>@elseif ($cell === false || $cell === null)<span class="no">—</span>@else{{ $cell }}@endif</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section white">
    <div class="wrap split top">
        <div class="narrow"><h2 style="font-size:clamp(28px,3vw,40px)">{{ site_md($c['faq_title']) }}</h2></div>
        <div class="wide">@include('site.partials.faq', ['faqs' => $pricing['faqs']])</div>
    </div>
</section>

@include('site.partials.cta', ['cta' => $c['cta'], 'class' => ''])
@endsection
