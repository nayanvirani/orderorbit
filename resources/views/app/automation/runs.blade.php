@extends('layouts.embedded')

@section('title', 'Workflow runs')

@section('content')
@php($triggers = \App\Automation\Definition::catalog()['triggers'])
<s-page heading="Workflow runs">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.automation.index') }}">Automation</s-link>
    @include('app.automation._nav')

    <s-section>
        <div class="oo-inline" style="margin-bottom:12px">
            @foreach (['' => 'All', 'running' => 'Running', 'waiting' => 'Waiting', 'completed' => 'Completed', 'failed' => 'Failed'] as $value => $label)
                <s-button href="{{ app_route('app.automation.runs', array_filter(['status' => $value])) }}" variant="{{ request('status', '') === $value ? 'primary' : 'secondary' }}">{{ $label }}</s-button>
            @endforeach
        </div>
        @if ($runs->isEmpty())
            <s-paragraph>No runs yet. Runs appear here once an enabled workflow is triggered.</s-paragraph>
        @else
            <table class="oo-table stack">
                <thead><tr><th>Run</th><th>Workflow</th><th>Trigger</th><th>For</th><th>Status</th><th>Started</th><th>Duration</th></tr></thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td data-label="Run"><s-link href="{{ app_route('app.automation.runs.show', ['run' => $run->id]) }}">#{{ $run->id }}</s-link>@if ($run->test) <s-badge>Test</s-badge>@endif</td>
                            <td data-label="Workflow">{{ $run->workflow?->name ?? '—' }}</td>
                            <td data-label="Trigger">{{ $triggers[$run->trigger]['label'] ?? $run->trigger }}</td>
                            <td data-label="For">{{ $run->subject }}</td>
                            <td data-label="Status">@include('app.automation._status', ['status' => $run->status])@if ($run->status === 'waiting' && $run->resume_at) <span class="oo-muted">until {{ $run->resume_at->toDayDateTimeString() }}</span>@endif</td>
                            <td data-label="Started">{{ $run->created_at->diffForHumans() }}</td>
                            <td data-label="Duration">{{ $run->duration() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $runs->links() }}
        @endif
    </s-section>
</s-page>
@endsection
