@extends('layouts.site')

@section('title', 'Pricing | OrderOrbit — Starter, Growth & Scale')
@section('description', 'OrderOrbit plans from $9.99/mo, billed monthly through Shopify. Change or cancel anytime.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Pricing</span>
        <h1>Plans that <span class="grad-text">grow with your store.</span></h1>
        <p class="lead">Billed monthly through Shopify. Change or cancel anytime.</p>
    </div>
</section>

<section class="section tight" style="padding-top:8px">
    <div class="wrap">@include('site.partials.plan-cards')</div>
</section>

<section class="section tight">
    <div class="wrap">
        <div class="section-head reveal"><h2>Compare plans</h2></div>
        <div class="table-scroll reveal">
            <table class="compare">
                <thead><tr><th>Feature</th>@foreach ($plans as $key => $plan)<th>{{ $plan['name'] }}<div class="muted" style="font:600 13px var(--font-body)">${{ number_format($plan['price'], 2) }}/mo</div></th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($pricing['rows'] as $row)
                        <tr>
                            <td><b>{{ $row[0] }}</b></td>
                            @foreach (array_slice($row, 1) as $cell)
                                <td>
                                    @if ($cell === true)<x-icon name="check-circle" class="yes"/>
                                    @elseif ($cell === false)<span class="no">—</span>
                                    @else{{ $cell }}@endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="center muted" style="margin-top:16px">Billed through Shopify. Existing data is never deleted on downgrade; over-limit items are paused.</p>
    </div>
</section>

<section class="section tint">
    <div class="wrap">
        <div class="section-head reveal"><h2>Pricing FAQ</h2></div>
        @include('site.partials.faq', ['faqs' => $pricing['faqs']])
    </div>
</section>

@include('site.partials.cta', ['secondary' => 'how'])
@endsection
