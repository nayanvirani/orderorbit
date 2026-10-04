@extends('layouts.embedded')

@section('title', 'Automation emails')

@section('content')
<s-page inlineSize="large" heading="Emails">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.automation.index') }}">Automation</s-link>
    @include('app.automation._nav')

    <s-banner tone="info" heading="Email sending isn't connected yet">
        <s-paragraph>Workflows prepare their emails and keep them here. Sending starts once an email provider is connected; nothing has been sent to customers yet.</s-paragraph>
    </s-banner>

    <s-section heading="Prepared emails">
        @if ($emails->isEmpty())
            <s-paragraph>No emails yet. Workflows with a "Send email" step prepare them here.</s-paragraph>
        @else
            <table class="oo-table stack">
                <thead><tr><th>Subject</th><th>For</th><th>Status</th><th>Prepared</th></tr></thead>
                <tbody>
                    @foreach ($emails as $email)
                        <tr>
                            <td data-label="Subject"><details><summary>{{ $email->subject }}</summary><pre style="white-space:pre-wrap;font:inherit;margin:8px 0 0">{{ $email->body }}</pre></details></td>
                            <td data-label="For">{{ $email->customer_id ? 'Customer '.$email->customer_id : '—' }}</td>
                            <td data-label="Status">
                                @switch($email->status)
                                    @case('sent')<s-badge tone="success">Sent</s-badge>@break
                                    @case('failed')<s-badge tone="critical">Failed</s-badge>@break
                                    @default<s-badge tone="info">Waiting for email provider</s-badge>
                                @endswitch
                            </td>
                            <td data-label="Prepared">{{ $email->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $emails->links() }}
        @endif
    </s-section>
</s-page>
@endsection
