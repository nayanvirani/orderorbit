@extends('layouts.embedded')

@section('title', 'Event Explorer')

@php
    $byName = collect($events)->keyBy('name');
    $trend = fn ($now, $before) => \App\Services\Analytics\Analytics::trend((float) $now, $before ? (float) $before : null);
    $groups = ['Shopify storefront events' => \App\Services\Analytics\Events::STANDARD, 'OrderOrbit Space events' => \App\Services\Analytics\Events::ORDERORBIT];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v={{ filemtime(public_path('css/analytics.css')) }}">
@endpush

@section('content')
<s-page heading="Event Explorer">
    @include('app.analytics._nav', ['lockedTitle' => 'Event Explorer is on Growth and Scale'])

    @unless ($locked)
        <form method="GET" action="{{ app_route('app.analytics.events') }}" class="an-filters">
            <input type="hidden" name="days" value="{{ $days }}">
            @if ($selected)<input type="hidden" name="event" value="{{ $selected }}"><input type="hidden" name="by" value="{{ $dimension }}">@endif
            <label class="oo-field">Device<select name="f[device]"><option value="">All</option>@foreach (['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'] as $k => $l)<option value="{{ $k }}" @selected(($filters['device'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></label>
            <label class="oo-field">Market<input type="text" name="f[country]" value="{{ $filters['country'] ?? '' }}" maxlength="2" placeholder="US"></label>
            <label class="oo-field">UTM source<input type="text" name="f[source]" value="{{ $filters['source'] ?? '' }}" maxlength="60"></label>
            <label class="oo-field">UTM campaign<input type="text" name="f[campaign]" value="{{ $filters['campaign'] ?? '' }}" maxlength="100"></label>
            <label class="oo-field">Experience<select name="f[experience]"><option value="">All</option>@foreach ($experiences as $h => $e)<option value="{{ $h }}" @selected(($filters['experience'] ?? '') === $h)>{{ $e->name }}</option>@endforeach</select></label>
            <s-button type="submit">Apply</s-button>
            <s-button href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" target="_blank" variant="tertiary">Export CSV</s-button>
        </form>
        @if ($selected)
            <s-section heading="{{ \App\Services\Analytics\Events::label($selected) }}">
                <p class="oo-muted oo-small"><span class="oo-code">{{ $selected }}</span> · {{ number_format($byName[$selected]['total'] ?? 0) }} events from {{ number_format($byName[$selected]['visitors'] ?? 0) }} visitors · <a href="{{ request()->fullUrlWithQuery(['event' => null, 'by' => null]) }}">Back to all events</a></p>
                <nav class="bx-tabs" aria-label="Break down by">
                    @foreach (\App\Services\Analytics\Events::DIMENSIONS as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['by' => $key]) }}" class="{{ $dimension === $key ? 'on' : '' }}">{{ $label }}</a>
                    @endforeach
                </nav>
                @if (! $breakdown)
                    <s-paragraph><span class="oo-muted">No events in this period.</span></s-paragraph>
                @else
                    @php($top = max(1, collect($breakdown)->max('total')))
                    <table class="oo-table stack an-break">
                        <thead><tr><th>{{ \App\Services\Analytics\Events::DIMENSIONS[$dimension] }}</th><th>Events</th><th>Visitors</th><th class="an-bar-col"></th></tr></thead>
                        <tbody>
                            @foreach ($breakdown as $row)
                                <tr>
                                    <td data-label="{{ \App\Services\Analytics\Events::DIMENSIONS[$dimension] }}">
                                        @if ($row['value'] === null)<span class="oo-muted">Not set</span>
                                        @elseif ($dimension === 'experience_handle'){{ $experiences[$row['value']]->name ?? $row['value'] }}
                                        @elseif ($dimension === 'product_id'){{ $row['label'] ?: 'Product '.$row['value'] }}
                                        @elseif ($dimension === 'page_type'){{ \App\Services\Analytics\Events::PAGE_TYPES[$row['value']] ?? $row['value'] }}
                                        @else{{ $row['value'] }}@endif
                                    </td>
                                    <td data-label="Events">{{ number_format($row['total']) }}</td>
                                    <td data-label="Visitors">{{ number_format($row['visitors']) }}</td>
                                    <td class="an-bar-col"><span class="an-bar"><i style="width:{{ $row['total'] / $top * 100 }}%"></i></span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </s-section>
        @endif

        @foreach ($groups as $heading => $catalogue)
            <s-section heading="{{ $heading }}">
                <div class="oo-scroll">
                    <table class="oo-table stack">
                        <thead><tr><th>Event</th><th>Events</th><th>Unique visitors</th><th>Sessions</th><th>Trend</th><th>Daily</th></tr></thead>
                        <tbody>
                            @foreach ($catalogue as $name => $label)
                                @php($r = $byName[$name] ?? null)
                                <tr class="{{ $r ? '' : 'an-dim' }}">
                                    <td data-label="Event">@if ($r)<a href="{{ request()->fullUrlWithQuery(['event' => $name]) }}">{{ $label }}</a>@else{{ $label }}@endif<br><span class="oo-muted oo-small">{{ $name }}</span></td>
                                    <td data-label="Events">{{ number_format($r['total'] ?? 0) }}</td>
                                    <td data-label="Unique visitors">{{ number_format($r['visitors'] ?? 0) }}</td>
                                    <td data-label="Sessions">{{ number_format($r['sessions'] ?? 0) }}</td>
                                    <td data-label="Trend">@if ($r && ($t = $trend($r['total'], $r['previous'])) !== null)<span class="ob-trend {{ $t > 0.5 ? 'up' : ($t < -0.5 ? 'down' : 'flat') }}">{{ $t > 0.5 ? '↑' : ($t < -0.5 ? '↓' : '→') }} {{ abs(round($t)) }}%</span>@else<span class="oo-muted">—</span>@endif</td>
                                    <td data-label="Daily">@if ($r && max($r['daily']) > 0)<x-app.sparkline :values="array_values($r['daily'])" />@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </s-section>
        @endforeach
        <p class="oo-muted oo-small">Events come from the OrderOrbit Space web pixel and only include shoppers who allow analytics. Trends compare with the previous {{ $days }} days.</p>
    @endunless
</s-page>
@endsection
