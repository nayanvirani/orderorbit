@extends('layouts.embedded')

@section('title', 'Bundles')

@php
    $canManage = request()->attributes->get('storeUser')?->can('manage_experiences');
    $typeLabel = fn ($e) => \App\Experiences\BundleSchema::TYPES[$e->draft_config['bundle_type'] ?? '']['label'] ?? 'Bundle';
    $summary = function ($e) {
        $c = $e->draft_config;
        $names = collect($c['offers'] ?? [])->flatMap(fn ($o) => array_merge($o['products'] ?? [], $o['product'] ?? []))
            ->merge($c['mix']['pool'] ?? [])->pluck('title')->filter()->unique()->take(3);
        $visibility = match ($c['settings']['visibility'] ?? 'all') {
            'products' => count($c['settings']['products'] ?? []).' selected products',
            'collections' => count($c['settings']['collections'] ?? []).' collections',
            default => 'All products',
        };

        return trim($visibility.($names->isNotEmpty() ? ' · '.$names->implode(', ') : ''));
    };
    $tabs = ['all' => 'All', 'active' => 'Active', 'scheduled' => 'Scheduled', 'draft' => 'Draft', 'paused' => 'Paused', 'archived' => 'Archived'];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
@endpush

@section('content')
<s-page heading="Bundles">
    @if ($canManage)
        <s-button slot="primary-action" variant="primary" href="{{ app_route('app.bundles.types') }}">Create bundle</s-button>
    @endif

    <x-app.hero eyebrow="Bundles" icon="package" tone="bundles" title="Sell more per order with <em>bundles.</em>"
        lead="Quantity breaks, mix & match, fixed packs and gift bundles. One bundle line in the cart, stock deducted per product." />

    <s-section>
        <div class="bx-overview">
            <div class="bx-overview-head"><strong>Overview</strong>
                <span class="bx-pill {{ $counts['active'] ? 'on' : '' }}">{{ $counts['active'] ? 'Bundles live' : 'No live bundles' }}</span>
            </div>
            <div class="ob-kpis">
                <div class="ob-kpi"><small>Live bundles</small><b>{{ $counts['active'] }}</b><span>Showing on product pages</span></div>
                <div class="ob-kpi"><small>Scheduled</small><b>{{ $counts['scheduled'] }}</b><span>Start automatically</span></div>
                <div class="ob-kpi"><small>Drafts</small><b>{{ $counts['draft'] }}</b><span>Not live yet</span></div>
                <div class="ob-kpi"><small>Bundle revenue · 30 days</small><b style="font-size:26px">{{ money(collect($stats)->sum('revenue'), $store->currency) }}</b><span>{{ number_format(collect($stats)->sum('orders')) }} orders</span></div>
            </div>
        </div>
    </s-section>

    <s-section>
        <nav class="bx-tabs" aria-label="Filter bundles">
            @foreach ($tabs as $key => $label)
                <a href="{{ app_route('app.bundles.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }} <span>({{ $counts[$key] }})</span></a>
            @endforeach
        </nav>

        @if ($bundles->isEmpty())
            <x-app.empty :title="$tab === 'all' ? 'No bundles yet' : 'Nothing here'" text="Quantity breaks, mix & match, fixed packs and gift bundles. Pick a type, choose a model and publish.">
                @if ($canManage)<s-button variant="primary" href="{{ app_route('app.bundles.types') }}">Create bundle</s-button>@endif
            </x-app.empty>
        @else
            <div class="oo-scroll">
                <table class="oo-table stack bx-table">
                    <thead><tr><th>Status</th><th>Title</th><th>Type</th><th>Views</th><th>Added to cart</th><th>Orders</th><th>Revenue</th><th style="text-align:right">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($bundles as $bundle)
                            @php($live = $bundle->status === 'published')
                            <tr>
                                <td data-label="Status">
                                    @if ($canManage && $bundle->status !== 'archived')
                                        <form method="POST" action="{{ app_route('app.bundles.toggle', ['bundle' => $bundle->id]) }}" @if ($live) data-confirm="Pause this bundle? It disappears from your store and its pricing stops." @endif>
                                            <button type="submit" class="bx-switch {{ $live ? 'on' : '' }}" role="switch" aria-checked="{{ $live ? 'true' : 'false' }}" aria-label="{{ $live ? 'Pause' : 'Publish' }} {{ $bundle->name }}"><i></i></button>
                                        </form>
                                    @else
                                        @include('app.cro._status', ['experience' => $bundle])
                                    @endif
                                </td>
                                <td data-label="Title">
                                    <a class="bx-title" href="{{ $canManage ? app_route('app.bundles.edit', ['bundle' => $bundle->id]) : app_route('app.cro.experiences.show', ['experience' => $bundle->id]) }}">{{ $bundle->name }}</a>
                                    <span class="bx-sub">{{ $summary($bundle) }}</span>
                                </td>
                                <td data-label="Type">{{ $typeLabel($bundle) }}</td>
                                @php($st = $stats[$bundle->handle] ?? ['views' => 0, 'adds' => 0, 'orders' => 0, 'revenue' => 0])
                                <td data-label="Views">{{ number_format($st['views']) }}</td>
                                <td data-label="Added to cart">{{ number_format($st['adds']) }}@if ($st['views']) <span class="oo-muted">({{ round($st['adds'] / $st['views'] * 100, 1) }}%)</span>@endif</td>
                                <td data-label="Orders">{{ number_format($st['orders']) }}</td>
                                <td data-label="Revenue">{{ money($st['revenue'], $store->currency) }}</td>
                                <td data-label="Actions">
                                    <div class="bx-actions">
                                        <a class="bx-icon" href="{{ app_route('app.cro.experiences.show', ['experience' => $bundle->id]) }}" title="Details and history" aria-label="Details">
                                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4C5.5 4 2.7 8 2 10c.7 2 3.5 6 8 6s7.3-4 8-6c-.7-2-3.5-6-8-6Zm0 9.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z" fill="currentColor"/></svg>
                                        </a>
                                        @if ($canManage && $bundle->status !== 'archived')
                                            <a class="bx-icon" href="{{ app_route('app.bundles.edit', ['bundle' => $bundle->id]) }}" title="Edit" aria-label="Edit">
                                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m13.6 3.4 3 3-9.1 9.1-3.7.7.7-3.7 9.1-9.1Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                                            </a>
                                            <form method="POST" action="{{ app_route('app.cro.experiences.duplicate', ['experience' => $bundle->id]) }}">
                                                <button type="submit" class="bx-icon" title="Duplicate" aria-label="Duplicate">
                                                    <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="7" y="7" width="9" height="9" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M13 4H6a2 2 0 0 0-2 2v7" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ app_route('app.cro.experiences.lifecycle', ['experience' => $bundle->id, 'action' => 'archive']) }}" data-confirm="Archive this bundle? It stops showing and its pricing stops. You can restore it later.">
                                                <button type="submit" class="bx-icon danger" title="Archive" aria-label="Archive">
                                                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14v3H3zM4.5 8v8h11V8M8 11h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </s-section>
</s-page>
@endsection
