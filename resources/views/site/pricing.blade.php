@extends('layouts.site')

@section('title', 'Pricing | OrderOrbit Space')
@section('description', 'Start free. Starter $14.99, Growth $29.99 and Scale $59.99 per month, stepping up with your store\'s sales and the features you need. Billed through Shopify.')

@section('content')
<div class="mn">
    <section class="mn-hero center">
        <div class="wrap">
            <span class="mn-kicker">Pricing</span>
            <h1>Start free. <em>Upgrade as your store grows.</em></h1>
            <p class="mn-lead">Every plan includes the core widgets and every template. Paid plans lift the limits, and higher plans add checkout, testing and automation as they're released.</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-wide">@include('site.partials.plan-cards', ['plans' => $plans])</div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Compare plans</h2>
            <div class="table-scroll">
                <table class="compare">
                    <thead><tr><th></th>@foreach ($plans as $key => $plan)<th>{{ $plan['name'] }}<div class="muted">{{ $plan['price'] > 0 ? '$'.number_format($plan['price'], 2).'/mo' : 'Free' }}</div></th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($pricing['rows'] as $row)
                            <tr>
                                <td>{{ $row[0] }}</td>
                                @foreach (array_slice($row, 1) as $cell)
                                    <td>@if ($cell === true)<x-icon name="check" class="yes"/>@elseif ($cell === false)<span class="no">—</span>@else<b>{{ $cell }}</b>@endif</td>
                                @endforeach
                            </tr>
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
            <p>You can change plans at any time. Nothing you've built is ever deleted.</p>
            <div class="ctas"><a class="btn primary lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked">Install on Shopify</a></div>
        </div>
    </section>
</div>
@endsection
