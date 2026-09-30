@extends('layouts.site')

@section('title', 'Pricing | OrderOrbit Space')
@section('description', 'Starter $9.99, Growth $29.99 and Scale $59.99 per month, billed through your Shopify invoice. Change or cancel anytime.')

@section('content')
<div class="mn">
    <section class="mn-hero center">
        <div class="wrap">
            <span class="mn-kicker">Pricing</span>
            <h1>Simple plans, <em>billed by Shopify.</em></h1>
            <p class="mn-lead">Pick a plan when you install. Upgrade, downgrade or cancel from your Shopify admin whenever you like.</p>
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
                    <thead><tr><th></th>@foreach ($plans as $key => $plan)<th>{{ $plan['name'] }}<div class="muted">${{ number_format($plan['price'], 2) }}/mo</div></th>@endforeach</tr></thead>
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
