@extends('layouts.embedded')

@section('title', 'Dashboard')

@php
    $done = collect($checklist)->where('done', true)->count();
    $total = count($checklist);
    $s = $summary;
    $p = $s['previous'];
    $trend = fn ($now, $before) => \App\Services\Analytics\Analytics::trend($now, $before);
    $spark = collect($s['daily'])->pluck('influenced')->all();
    $sparkAll = collect($s['daily'])->pluck('revenue')->all();
    $hour = now($store->timezone ?: 'UTC')->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $name = request()->attributes->get('storeUser')?->first_name;
    $actionLabels = ['experience.created' => 'created', 'experience.published' => 'published', 'experience.paused' => 'paused', 'experience.saved' => 'saved', 'experience.archived' => 'archived', 'experience.resumed' => 'resumed', 'experience.duplicated' => 'duplicated', 'billing.plan_activated' => 'activated a plan', 'store.installed' => 'installed the app', 'onboarding.goal_selected' => 'chose a goal'];
@endphp

@section('content')
<s-page heading="Home">
    <div class="ob-greet">
        <div>
            <h1>{{ $greeting }}{{ $name ? ', '.$name : '' }} 👋</h1>
            <p>Here's how {{ $store->name ?? 'your store' }} is doing over the last 30 days.</p>
        </div>
        <div class="ob-actions" style="margin:0">
            <s-button href="{{ app_route('app.analytics') }}">View analytics</s-button>
            <s-button variant="primary" href="{{ app_route('app.bundles.types') }}">Create a bundle</s-button>
        </div>
    </div>

    @foreach ($alerts as $alert)
        <s-banner tone="{{ $alert['tone'] }}">
            <s-paragraph>{{ $alert['text'] }}</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route($alert['route']) }}">{{ $alert['action'] }}</s-button>
        </s-banner>
    @endforeach

    <div class="ob-kpis" style="margin-bottom:16px">
        <x-app.kpi label="Revenue from offers" icon="sparkle" tone="analytics" :value="money($s['influenced_revenue'], $s['currency'])"
            :trend="$trend($s['influenced_revenue'], $p['influenced_revenue'])" :sub="number_format($s['influenced_orders']).' orders with an offer'" :spark="$spark" />
        <x-app.kpi label="Store revenue" icon="chart" tone="upsells" :value="money($s['revenue'], $s['currency'])"
            :trend="$trend($s['revenue'], $p['revenue'])" :sub="number_format($s['orders']).' '.\Illuminate\Support\Str::plural('order', $s['orders'])" :spark="$sparkAll" />
        <x-app.kpi label="Average order value" icon="cart" tone="gifts" :value="$s['aov'] === null ? '—' : money($s['aov'], $s['currency'])"
            :trend="$trend($s['aov'], $p['aov'])" :sub="$s['influenced_aov'] ? money($s['influenced_aov'], $s['currency']).' with an offer' : 'Per order'" />
        <x-app.kpi label="Conversion rate" icon="target" tone="countdown" :value="$s['conversion'] === null ? '—' : number_format($s['conversion'], 2).'%'"
            :trend="$trend($s['conversion'], $p['conversion'])" :sub="number_format($s['sessions']).' sessions'" />
    </div>

    <s-section heading="Your features">
        <div class="ob-types">
            @foreach ($features as $featureKey => $feature)
                <a class="ob-type t-{{ $feature['tone'] ?? 'default' }}" href="{{ isset($feature['module']) ? app_route($feature['module']) : app_route('app.features.show', ['feature' => $featureKey]) }}">
                    <span class="ob-type-head">
                        <x-app.icon :name="$feature['icon'] ?? 'sparkle'" />
                        <span class="ob-live {{ $feature['live'] ? 'on' : '' }}">{{ $feature['live'] ? $feature['live'].' live' : ($feature['total'] ? 'Not live' : 'Not set up') }}</span>
                    </span>
                    <h4>{{ $feature['label'] }}</h4>
                    <p>{{ $feature['description'] }}</p>
                    <div class="ob-row">{{ $feature['total'] ? 'Manage' : 'Set up' }} →</div>
                </a>
            @endforeach
        </div>
    </s-section>

    <div class="ob-split">
        <s-section heading="{{ $checklistDone ? 'Recent activity' : 'Get set up' }}">
            @unless ($checklistDone)
                <div class="ob-progress" aria-label="{{ $done }} of {{ $total }} done"><i style="width:{{ round($done / $total * 100) }}%"></i></div>
                <p class="oo-muted oo-small" style="margin:0 0 6px">{{ $done }} of {{ $total }} steps done</p>
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
            @else
                @include('app._activity', ['recent' => $recent, 'actionLabels' => $actionLabels])
            @endunless
        </s-section>

        <s-section heading="{{ $checklistDone ? 'Tips' : 'Recent activity' }}">
            @if ($checklistDone)
                <ul class="ob-activity">
                    <li><x-app.icon name="package" size="sm" tone="bundles" /><div>Quantity breaks with a "Most popular" middle tier usually lift units per order the most.</div></li>
                    <li><x-app.icon name="gift" size="sm" tone="gifts" /><div>Set your first gift milestone just above your current average order value.</div></li>
                    <li><x-app.icon name="clock" size="sm" tone="countdown" /><div>Use countdowns only for real deadlines — shoppers trust them more.</div></li>
                </ul>
            @else
                @include('app._activity', ['recent' => $recent, 'actionLabels' => $actionLabels])
            @endif
        </s-section>
    </div>
</s-page>
@endsection
