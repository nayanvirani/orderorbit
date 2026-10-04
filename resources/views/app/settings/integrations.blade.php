@extends('layouts.embedded')

@section('title', 'Settings · Integrations')

@php
    use App\Http\Controllers\App\IntegrationsController;
    $ok = fn ($on, $yes, $no) => '<s-badge tone="'.($on ? 'success' : 'warning').'">'.e($on ? $yes : $no).'</s-badge>';
@endphp

@section('content')
<s-page heading="Settings">
    @include('app.settings._tabs')

    <s-section heading="Shopify">
        <dl class="oo-kv">
            <dt>Connection</dt><dd>{!! $ok($store->isInstalled() && ! $store->missingScopes(), 'Connected', 'Needs attention') !!} <s-link href="{{ app_route('app.settings.store') }}">Details</s-link></dd>
            <dt>Theme app embed</dt><dd>{!! $ok($store->capability('online_store_2') !== false, 'Supported theme', 'Theme doesn\'t support app blocks') !!} <s-link href="{{ $store->themeEditorUrl('global') }}" target="_top">Open Theme Editor</s-link></dd>
            <dt>Web pixel (analytics)</dt><dd>{!! $ok((bool) $store->web_pixel_id, 'Connected', 'Not connected') !!} <span class="oo-muted oo-small">Last event {{ $lastPixelEvent ? \Illuminate\Support\Carbon::parse($lastPixelEvent)->diffForHumans() : 'not yet' }}</span></dd>
            <dt>Checkout blocks</dt><dd>{!! $ok((bool) $store->capability('checkout_blocks'), 'Available', 'Thank You and Order Status only (needs Shopify Plus)') !!}</dd>
            <dt>Customer accounts</dt><dd>{!! $ok($store->capability('new_customer_accounts') !== false, 'New customer accounts', 'Classic accounts: blocks unavailable') !!}</dd>
        </dl>
    </s-section>

    <s-section heading="Webhooks · last 30 days">
        <p class="oo-muted">Shopify tells OrderOrbit Space about these events. Each is verified with Shopify's signature and handled once.</p>
        <div class="oo-scroll">
            <table class="oo-table stack">
                <thead><tr><th>Event</th><th>Topic</th><th>Received</th><th>Last received</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach (IntegrationsController::TOPICS as $topic => $label)
                        @php($r = $receipts[$topic] ?? null)
                        <tr>
                            <td data-label="Event">{{ $label }}</td>
                            <td data-label="Topic"><span class="oo-code">{{ $topic }}</span></td>
                            <td data-label="Received">{{ $r ? number_format($r->n) : 0 }}</td>
                            <td data-label="Last received">{{ $r ? \Illuminate\Support\Carbon::parse($r->last_at)->diffForHumans() : '—' }}</td>
                            <td data-label="Status">@if ($r && $r->unprocessed)<s-badge tone="warning">{{ $r->unprocessed }} not processed</s-badge>@elseif ($r)<s-badge tone="success">OK</s-badge>@else<span class="oo-muted">Subscribed</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </s-section>

    <s-section heading="OrderOrbit Space extensions">
        <ul class="oo-list">
            <li><strong>Theme app extension</strong>: storefront blocks and the app embed.</li>
            <li><strong>Web pixel</strong>: consent-aware analytics.</li>
            <li><strong>Checkout UI extension</strong>: checkout, Thank You and Order Status blocks.</li>
            <li><strong>Customer account extension</strong>: account blocks and "Buy again".</li>
            <li><strong>Post-purchase extension</strong>: the one-click offer after payment.</li>
            <li><strong>Discount function and cart transform</strong>: bundle prices and offer savings at checkout.</li>
        </ul>
    </s-section>

    <s-section heading="Coming integrations">
        <div class="ob-kpis">
            @foreach (['Klaviyo' => 'Send segments and events to your email flows.', 'Judge.me and Yotpo' => 'Real reviews in review blocks and requests.', 'Google Analytics 4' => 'OrderOrbit events in GA4.', 'Gorgias' => 'Support tickets with order context.'] as $name => $text)
                <div class="ob-kpi"><small>Planned</small><b style="font-size:18px">{{ $name }}</b><span>{{ $text }}</span></div>
            @endforeach
        </div>
    </s-section>
</s-page>
@endsection
