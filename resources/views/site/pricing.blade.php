@extends('layouts.site')

@section('title', 'Pricing | OrderOrbit Space')
@section('description', $page['lead'].' '.collect($plans)->map(fn ($p) => $p['name'].' '.($p['price'] > 0 ? '$'.number_format($p['price'], 2).'/mo' : 'free'))->implode(', ').'. '.$page['billing_note'])

@section('content')
<div class="mn">
    <section class="mn-hero center">
        <div class="wrap">
            <span class="mn-kicker">Pricing</span>
            <h1>{{ $page['headline'] }}</h1>
            <p class="mn-lead">{{ $page['lead'] }}</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-wide">
            @include('site.partials.plan-cards', ['plans' => $plans])
            <p class="pricing-note">{{ $page['billing_note'] }}@if ($page['trial_note']) {{ $page['trial_note'] }}@endif</p>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>{{ $page['compare_title'] }}</h2>
            <div class="table-scroll">
                <table class="compare">
                    <thead><tr><th></th>@foreach ($plans as $key => $plan)<th>{{ $plan['name'] }}<div class="muted">{{ $plan['price'] > 0 ? '$'.number_format($plan['price'], 2).'/mo' : 'Free' }}</div></th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($compare as $group)
                            <tr class="compare-group"><td colspan="{{ count($plans) + 1 }}">{{ $group['group'] }}</td></tr>
                            @foreach ($group['rows'] as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    @foreach ($plans as $key => $plan)
                                        @php($cell = $row['values'][$key] ?? false)
                                        <td>@if ($cell === true)<x-icon name="check" class="yes"/>@elseif ($cell === false || $cell === null)<span class="no">—</span>@else<b>{{ $cell }}</b>@endif</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow">
            <h2>Questions about billing</h2>
            @include('site.partials.faq', ['faqs' => $pricing['faqs']])
        </div>
    </section>

    <section class="mn-section mn-cta">
        <div class="mn-narrow">
            <h2>Start with the plan that fits today</h2>
            <p>{{ $page['billing_note'] }} Nothing you've built is ever deleted.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a></div>
        </div>
    </section>
</div>
@endsection
