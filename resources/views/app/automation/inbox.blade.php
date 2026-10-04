@extends('layouts.embedded')

@section('title', 'Automation inbox')

@section('content')
<s-page heading="Inbox">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.automation.index') }}">Automation</s-link>
    @include('app.automation._nav')

    <s-section heading="To do">
        @if ($open->isEmpty())
            <s-paragraph>Nothing to do. Tasks and notifications from your workflows appear here.</s-paragraph>
        @else
            <table class="oo-table stack">
                <thead><tr><th>Item</th><th>Type</th><th>Due</th><th></th></tr></thead>
                <tbody>
                    @foreach ($open as $item)
                        <tr>
                            <td data-label="Item"><strong>{{ $item->title }}</strong>@if ($item->body)<br><span class="oo-muted">{{ $item->body }}</span>@endif</td>
                            <td data-label="Type">{{ $item->kind === 'task' ? 'Task' : 'Notification' }}</td>
                            <td data-label="Due">{{ $item->due_at ? $item->due_at->toFormattedDateString() : '—' }}</td>
                            <td><form method="POST" action="{{ app_route('app.automation.inbox.done', ['item' => $item->id]) }}"><s-button type="submit">{{ $item->kind === 'task' ? 'Mark done' : 'Dismiss' }}</s-button></form></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </s-section>

    @if ($done->isNotEmpty())
        <s-section heading="Done recently">
            @foreach ($done as $item)<s-paragraph><span class="oo-muted">✓ {{ $item->title }} · {{ $item->done_at->diffForHumans() }}</span></s-paragraph>@endforeach
        </s-section>
    @endif
</s-page>
@endsection
