@extends('layouts.site')

@section('title', 'Pricing | OrderOrbit Space — Starter, Growth & Scale')
@section('description', 'OrderOrbit Space plans from $9.99/mo, billed monthly through Shopify. Change or cancel anytime.')

@section('content')
<section class="page-hero sky">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Pricing</span>
        <h1>Plans that grow <em>with your store.</em></h1>
        <p class="lead">Billed monthly through Shopify. Change or cancel anytime.</p>
    </div>
</section>

<section class="section tight" style="padding-top:0">
    <div class="wrap">@include('site.partials.plan-cards')</div>
</section>

<section class="section alt">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Compare</span>
            <h2>Every plan, <em>side by side.</em></h2>
        </div>
        <div class="table-scroll reveal">
            <table class="compare">
                <thead><tr><th></th>@foreach ($plans as $key => $plan)<th>{{ $plan['name'] }}<div class="muted">${{ number_format($plan['price'], 2) }}/mo</div></th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($pricing['rows'] as $row)
                        <tr>
                            <td>{{ $row[0] }}</td>
                            @foreach (array_slice($row, 1) as $cell)
                                <td>
                                    @if ($cell === true)<x-icon name="check" class="yes"/>
                                    @elseif ($cell === false)<span class="no">—</span>
                                    @else<b>{{ $cell }}</b>@endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="muted" style="margin-top:20px">Billed through Shopify. Existing data is never deleted on downgrade; over-limit items are paused.</p>
    </div>
</section>

<section class="section">
    <div class="wrap faq-layout">
        <div class="section-head reveal">
            <span class="eyebrow"><span class="dot"></span>Pricing FAQ</span>
            <h2>Billing, <em>answered.</em></h2>
        </div>
        @include('site.partials.faq', ['faqs' => $pricing['faqs']])
    </div>
</section>

@include('site.partials.cta', ['secondary' => 'how'])
@endsection
