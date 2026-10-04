@extends('layouts.embedded')

@section('title', 'Settings · Privacy')

@php($canManage = request()->attributes->get('storeUser')?->can('manage_settings'))

@section('content')
<s-page heading="Settings">
    @include('app.settings._tabs')

    <s-section heading="Consent">
        <s-paragraph>OrderOrbit Space analytics run in a Shopify web pixel, which follows your store's cookie banner and Shopify's Customer Privacy settings: events are only recorded for shoppers who allow analytics. Storefront experiences still show to everyone.</s-paragraph>
        <s-paragraph>No names, emails or addresses are stored. Shoppers are an anonymous visitor id and, when signed in or after buying, a customer number. Shopify's customer data requests and deletion requests are handled automatically.</s-paragraph>
    </s-section>

    <form method="POST" action="{{ app_route('app.settings.privacy.update') }}">
        <s-section heading="Retention and collection">
            <label class="oo-field" style="max-width:320px">Keep analytics events for
                <select name="retention_months" @disabled(! $canManage)>
                    @foreach ([3 => '3 months', 6 => '6 months', 13 => '13 months (default)'] as $m => $label)<option value="{{ $m }}" @selected($store->privacy('retention_months') === $m)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <p class="oo-muted oo-small">Older events are deleted every night. Shorter retention also shortens what funnels, journeys and attribution can look back on.</p>
            <label class="oo-radio"><input type="checkbox" name="browsing_events" value="1" @checked($store->privacy('browsing_events')) @disabled(! $canManage)><span><strong>Record browsing events</strong><small>Page, product, collection, search, cart and checkout steps, used by Event Explorer, funnels and journeys. Offer views, clicks and orders are always recorded for offer analytics and A/B tests.</small></span></label>
            <label class="oo-radio"><input type="checkbox" name="journeys" value="1" @checked($store->privacy('journeys')) @disabled(! $canManage)><span><strong>Link visits to customer numbers</strong><small>Lets customer journeys follow a customer across devices and repeat purchases. When off, no customer numbers are kept.</small></span></label>
            @if ($canManage)<s-button type="submit" variant="primary">Save</s-button>@endif
        </s-section>
    </form>

    <s-section heading="Export and deletion">
        <dl class="oo-kv">
            <dt>Analytics events stored</dt><dd>{{ number_format($events) }}</dd>
            <dt>Oldest event</dt><dd>{{ $oldest ? \Illuminate\Support\Carbon::parse($oldest)->toFormattedDateString() : '—' }}</dd>
        </dl>
        @if ($canManage)
            <div class="oo-inline" style="margin-top:12px">
                <s-button href="{{ app_route('app.settings.privacy.export') }}" target="_blank">Export analytics (CSV)</s-button>
                <form method="POST" action="{{ app_route('app.settings.privacy.delete') }}" data-confirm="Delete every analytics event for your store? Reports, funnels, journeys and A/B test results start again from zero. This can't be undone.">
                    <s-button type="submit" tone="critical" variant="tertiary">Delete all analytics data</s-button>
                </form>
            </div>
        @endif
        <p class="oo-muted oo-small">When you uninstall, Shopify asks apps to delete store data 48 hours later; OrderOrbit Space deletes it then.</p>
    </s-section>
</s-page>
@endsection
