@extends('layouts.embedded')

@section('title', 'Run #'.$run->id)

@section('content')
<s-page heading="Run #{{ $run->id }}{{ $run->test ? ' (test)' : '' }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.automation.runs') }}">Runs</s-link>
    @include('app.automation._nav')

    @if ($run->test)
        <s-banner tone="info" heading="Test run">
            <s-paragraph>This run used a sample order and changed nothing: waits were skipped and each action says what it would do.</s-paragraph>
        </s-banner>
    @endif

    <s-section heading="Summary">
        <dl class="oo-kv">
            <dt>Workflow</dt><dd>@if ($run->workflow)<s-link href="{{ app_route('app.automation.edit', ['workflow' => $run->workflow_id]) }}">{{ $run->workflow->name }}</s-link>@else — @endif {{ $run->version && $run->version->version ? '· version '.$run->version->version : '' }}</dd>
            <dt>For</dt><dd>{{ $run->subject }}</dd>
            <dt>Status</dt><dd>@include('app.automation._status', ['status' => $run->status])@if ($run->status === 'waiting' && $run->resume_at) <span class="oo-muted">until {{ $run->resume_at->toDayDateTimeString() }} UTC</span>@endif</dd>
            <dt>Trigger</dt><dd>{{ \App\Automation\Definition::catalog()['triggers'][$run->trigger]['label'] ?? $run->trigger }}</dd>
            <dt>Started</dt><dd>{{ $run->created_at->toDayDateTimeString() }} UTC</dd>
            @if ($run->finished_at)<dt>Finished</dt><dd>{{ $run->finished_at->toDayDateTimeString() }} UTC</dd>@endif
            <dt>Duration</dt><dd>{{ $run->duration() }}{{ $run->finished_at ? '' : ' so far' }}</dd>
            @if ($run->attempts)<dt>Attempts</dt><dd>{{ $run->attempts }}</dd>@endif
            @if ($run->error)<dt>Last error</dt><dd>{{ $run->error }}</dd>@endif
            <dt>Idempotency key</dt><dd><span class="oo-code">{{ $run->idempotency_key }}</span> <span class="oo-muted oo-small">The same Shopify event never starts this workflow twice.</span></dd>
        </dl>
        @if ($run->canRetry())
            <form method="POST" action="{{ app_route('app.automation.runs.retry', ['run' => $run->id]) }}" style="margin-top:12px">
                <s-button type="submit" variant="primary">Retry from the failed step</s-button>
                <span class="oo-muted oo-small">Steps that already finished won't run again.</span>
            </form>
        @endif
    </s-section>

    <s-section heading="Log">
        <table class="oo-table stack">
            <thead><tr><th>Step</th><th>What happened</th><th>Result</th><th>When</th></tr></thead>
            <tbody>
                @foreach ($run->logs as $log)
                    <tr>
                        <td data-label="Step">{{ $log->kind === 'end' ? '—' : $log->step + 1 }}</td>
                        <td data-label="What happened">{{ $log->message }}</td>
                        <td data-label="Result">@include('app.automation._status', ['status' => $log->status])</td>
                        <td data-label="When">{{ $log->created_at->format('M j, H:i:s') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </s-section>
</s-page>
@endsection
