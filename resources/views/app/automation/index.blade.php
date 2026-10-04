@extends('layouts.embedded')

@section('title', 'Automation')

@php($catalog = \App\Automation\Definition::catalog())

@section('content')
<s-page heading="Automation">
    <x-app.hero eyebrow="Automation" title="Follow up <em>automatically.</em>" lead="Workflows react to orders, customers and OrderOrbit events: wait, check conditions, then tag, create codes, prepare emails, notify your team or call a webhook." icon="bolt" tone="default">
        <s-button variant="primary" href="{{ app_route('app.automation.templates') }}">Start from a template</s-button>
        <form method="POST" action="{{ app_route('app.automation.store') }}"><s-button type="submit">Blank workflow</s-button></form>
    </x-app.hero>

    @include('app.automation._nav')

    <s-section>
        <div class="ob-kpis">
            <div class="ob-kpi"><small>Enabled workflows</small><b>{{ $stats['enabled'] }}</b></div>
            <div class="ob-kpi"><small>Runs · 30 days</small><b>{{ number_format($stats['runs']) }}</b></div>
            <div class="ob-kpi"><small>Waiting</small><b>{{ number_format($stats['waiting']) }}</b></div>
            <div class="ob-kpi"><small>Failed · 30 days</small><b>{{ number_format($stats['failed']) }}</b></div>
        </div>
    </s-section>

    <s-section heading="Workflows">
        @if ($workflows->isEmpty())
            <s-paragraph>No workflows yet. Templates cover review requests, welcomes, VIPs, reorder reminders, win-backs and more.</s-paragraph>
            <s-button href="{{ app_route('app.automation.templates') }}">Browse templates</s-button>
        @else
            <table class="oo-table stack">
                <thead><tr><th>Workflow</th><th>Starts when</th><th>Status</th><th>Runs · 30 days</th><th>Last run</th></tr></thead>
                <tbody>
                    @foreach ($workflows as $w)
                        <tr>
                            <td data-label="Workflow"><s-link href="{{ app_route('app.automation.edit', ['workflow' => $w->id]) }}">{{ $w->name }}</s-link>@if ($w->has_unpublished_changes && $w->published_version_id) <s-badge>Unpublished changes</s-badge>@endif</td>
                            <td data-label="Starts when">{{ $catalog['triggers'][$w->trigger]['label'] ?? $w->trigger }}</td>
                            <td data-label="Status">
                                @switch($w->status)
                                    @case('enabled')<s-badge tone="success">Enabled</s-badge>@break
                                    @case('disabled')<s-badge>Disabled</s-badge>@break
                                    @default<s-badge tone="info">Draft</s-badge>
                                @endswitch
                            </td>
                            <td data-label="Runs">{{ number_format($w->runs_30d) }}@if ($w->failed_30d) <span class="oo-muted">· {{ $w->failed_30d }} failed</span>@endif</td>
                            <td data-label="Last run">{{ $w->last_run_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </s-section>
</s-page>
@endsection
