@extends('layouts.embedded')

@section('title', 'Personalization rules')

@php
    use App\Services\Audiences\Audiences;
    $canManage = request()->attributes->get('storeUser')?->can('manage_experiences');
    $who = function ($r) use ($segmentNames) {
        $parts = collect($r->segments ?? [])->map(fn ($id) => $segmentNames[$id] ?? 'Archived segment')->implode(' or ');
        $c = $r->conditions ?? [];
        $live = array_filter([
            isset($c['device']) ? ucfirst($c['device']) : null,
            isset($c['cart_min']) ? 'cart ≥ '.$c['cart_min'] : null,
            isset($c['cart_max']) ? 'cart ≤ '.$c['cart_max'] : null,
            isset($c['utm_source']) ? 'UTM source '.$c['utm_source'] : null,
            isset($c['utm_campaign']) ? 'campaign '.$c['utm_campaign'] : null,
        ]);
        return trim(($parts ?: '').($parts && $live ? ' + ' : '').implode(', ', $live)) ?: 'Everyone';
    };
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/audiences.css') }}?v={{ filemtime(public_path('css/audiences.css')) }}">
@endpush

@section('content')
<s-page heading="Audiences">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.audiences.segments') }}">Audiences</s-link>
    @include('app.audiences._nav')

    <s-section heading="Personalization rules">
        <p class="oo-muted">If a shopper matches a rule, it shows an experience only to them, shows it in another template, or hides it. Rules run top to bottom: for each experience, the first rule that matches wins.</p>
        @foreach ($conflicts as $names)
            <s-banner tone="warning">“{{ implode('”, “', $names) }}” target the same experience. When a shopper matches more than one, the one higher in the list wins.</s-banner>
        @endforeach
        @if ($canManage)<div style="margin:12px 0"><s-button href="{{ app_route('app.audiences.rules.create') }}" variant="primary">Create rule</s-button></div>@endif
        @if ($rules->isEmpty())
            <s-paragraph><span class="oo-muted">No rules yet. Examples: returning customers with a cart over $75 → show the premium upsell; mobile shoppers → show the bundle in its compact template; 3+ orders → show the VIP offer.</span></s-paragraph>
        @else
            <ol class="au-rule-list">
                @foreach ($rules as $r)
                    <li class="{{ $r->enabled ? '' : 'is-off' }}">
                        <span class="au-pri">{{ $loop->iteration }}</span>
                        <div class="au-rule-main">
                            <strong>@if ($canManage)<s-link href="{{ app_route('app.audiences.rules.edit', ['rule' => $r->id]) }}">{{ $r->name }}</s-link>@else{{ $r->name }}@endif</strong>
                            <span class="oo-small"><b>If</b> {{ $who($r) }} <b>then</b> {{ ['show' => 'show', 'swap' => 'show in another template:', 'hide' => 'hide'][$r->outcome] }} {{ $r->outcome === 'swap' ? (\App\Experiences\Registry::template($r->experience->type, (string) $r->template_key)['name'] ?? $r->template_key).' ·' : '' }} {{ $r->experience->name ?? 'a removed experience' }}</span>
                            @unless ($r->enabled)<s-badge>Disabled</s-badge>@endunless
                        </div>
                        @if ($canManage)
                            <div class="au-rule-actions">
                                @foreach (['up' => '↑', 'down' => '↓'] as $action => $arrow)
                                    <form method="POST" action="{{ app_route('app.audiences.rules.action', ['rule' => $r->id, 'action' => $action]) }}"><button type="submit" class="au-icon" aria-label="Move {{ $action }}" @disabled(($action === 'up' && $loop->parent->first) || ($action === 'down' && $loop->parent->last))>{{ $arrow }}</button></form>
                                @endforeach
                                <form method="POST" action="{{ app_route('app.audiences.rules.action', ['rule' => $r->id, 'action' => 'toggle']) }}"><s-button type="submit" variant="tertiary">{{ $r->enabled ? 'Disable' : 'Enable' }}</s-button></form>
                                <form method="POST" action="{{ app_route('app.audiences.rules.action', ['rule' => $r->id, 'action' => 'delete']) }}" data-confirm="Delete this rule?"><s-button type="submit" variant="tertiary" tone="critical">Delete</s-button></form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </s-section>
</s-page>
@endsection
