@extends('layouts.embedded')

@section('title', 'Dashboard')

@php
    $done = collect($checklist)->where('done', true)->count();
    $total = count($checklist);
@endphp

@section('content')
<s-page heading="Dashboard">
    <x-app.hero :eyebrow="$store->name ?? $store->shop_domain" title="Your store, <em>in orbit.</em>"
        lead="Create an experience, place it in your theme, and watch what it earns — then test, personalize and automate from the same numbers.">
        <s-button variant="primary" href="{{ app_route('app.cro.experiences.create') }}">Create experience</s-button>
        <s-button href="{{ app_route('app.templates') }}">Browse templates</s-button>
    </x-app.hero>

    @foreach ($alerts as $alert)
        <s-banner tone="{{ $alert['tone'] }}">
            <s-paragraph>{{ $alert['text'] }}</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route($alert['route']) }}">{{ $alert['action'] }}</s-button>
        </s-banner>
    @endforeach

    <s-section heading="Performance">
        <div class="ob-kpis">
            @foreach (['Revenue influenced', 'Conversion rate', 'AOV', 'CRO revenue'] as $kpi)
                <div class="ob-kpi"><small>{{ $kpi }}</small><b>—</b><span>Collecting data</span></div>
            @endforeach
        </div>
    </s-section>

    @unless ($checklistDone)
        <s-section heading="Set up OrderOrbit">
            <div class="ob-progress" aria-label="{{ $done }} of {{ $total }} done"><i style="width:{{ round($done / $total * 100) }}%"></i></div>
            <p class="oo-muted oo-small" style="margin:0 0 6px">{{ $done }} of {{ $total }} done</p>
            <ol class="ob-checklist">
                @foreach ($checklist as $item)
                    <li class="{{ $item['done'] ? 'done' : '' }}">
                        @if (! $item['done'] && isset($item['route']))
                            <a href="{{ app_route($item['route']) }}">{{ $item['label'] }} →</a>
                        @else
                            <span>{{ $item['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </s-section>
    @endunless

    <s-section heading="Recent activity">
        @forelse ($recent as $log)
            <s-paragraph><strong>{{ $log->actor?->displayName() ?? 'OrderOrbit' }}</strong> · {{ str_replace(['.', '_'], [' ', ' '], $log->action) }} <span class="oo-muted">· {{ $log->created_at?->diffForHumans() }}</span></s-paragraph>
        @empty
            <x-app.empty title="Nothing here yet" text="Your team's changes — new experiences, publishes, plan changes — show up here." />
        @endforelse
        @if (request()->attributes->get('storeUser')?->can('view_activity'))
            <s-link href="{{ app_route('app.settings.activity') }}">View all activity</s-link>
        @endif
    </s-section>
</s-page>
@endsection
