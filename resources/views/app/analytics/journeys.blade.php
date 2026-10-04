@extends('layouts.embedded')

@section('title', 'Customer journeys')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v={{ filemtime(public_path('css/analytics.css')) }}">
@endpush

@section('content')
<s-page inlineSize="large" heading="Customer journeys">
    @include('app.analytics._nav', ['lockedTitle' => 'Customer journeys are on Growth and Scale'])

    @if ($people)
        <s-section>
            <nav class="bx-tabs" aria-label="Shoppers">
                <a href="{{ request()->fullUrlWithQuery(['all' => null, 'page' => null]) }}" class="{{ $buyers ? 'on' : '' }}">Shoppers who bought</a>
                <a href="{{ request()->fullUrlWithQuery(['all' => 1, 'page' => null]) }}" class="{{ $buyers ? '' : 'on' }}">All shoppers</a>
            </nav>
            @if ($people->isEmpty())
                <s-paragraph><span class="oo-muted">No shoppers in this period yet. Journeys build up from the visits and orders the pixel records.</span></s-paragraph>
            @else
                <table class="oo-table stack">
                    <thead><tr><th>Shopper</th><th>First seen</th><th>Last active</th><th>Sessions</th><th>Orders</th><th>Revenue</th><th>Source</th></tr></thead>
                    <tbody>
                        @foreach ($people as $p)
                            <tr>
                                <td data-label="Shopper"><s-link href="{{ app_route('app.analytics.journey', ['visitor' => $p->visitor_id]) }}">{{ $p->customer_id ? 'Customer '.$p->customer_id : 'Visitor '.substr($p->visitor_id, 0, 8) }}</s-link>@if ($p->total_orders > 1) <s-badge tone="success">Repeat</s-badge>@endif</td>
                                <td data-label="First seen">{{ \Illuminate\Support\Carbon::parse($p->first_seen)->toFormattedDateString() }}</td>
                                <td data-label="Last active">{{ \Illuminate\Support\Carbon::parse($p->last_seen)->diffForHumans() }}</td>
                                <td data-label="Sessions">{{ number_format($p->sessions) }}</td>
                                <td data-label="Orders">{{ number_format($p->orders) }}</td>
                                <td data-label="Revenue">{{ $p->orders ? money($p->revenue, $store->currency) : '—' }}</td>
                                <td data-label="Source">{{ $p->source ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $people->links() }}
            @endif
            <p class="oo-muted oo-small">Shoppers are identified by Shopify's anonymous visitor id, and by customer number once they sign in or buy. No names, emails or addresses are stored. A customer's data is deleted when Shopify asks (customer redaction).</p>
        </s-section>
    @endif
</s-page>
@endsection
