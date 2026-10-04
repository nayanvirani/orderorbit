@extends('layouts.embedded')

@section('title', $ticket->reference())

@php
    use App\Models\Support\Ticket;
    $tone = ['open' => 'info', 'pending' => 'warning', 'resolved' => 'success', 'closed' => 'neutral'];
@endphp

@section('content')
<s-page heading="{{ $ticket->subject }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.support.index') }}">Support</s-link>
    @if (request('error'))<s-banner tone="critical">{{ request('error') }}</s-banner>@endif

    <s-section>
        <dl class="oo-kv">
            <dt>Ticket</dt><dd>{{ $ticket->reference() }}</dd>
            <dt>Status</dt><dd><s-badge tone="{{ $tone[$ticket->status] }}">{{ Ticket::STATUSES[$ticket->status] }}</s-badge></dd>
            <dt>Category</dt><dd>{{ Ticket::CATEGORIES[$ticket->category] ?? $ticket->category }} · {{ Ticket::PRIORITIES[$ticket->priority] ?? $ticket->priority }} priority</dd>
            <dt>Opened</dt><dd>{{ $ticket->created_at->toDayDateTimeString() }}</dd>
            @if ($ticket->resolution)<dt>Resolution</dt><dd>{{ $ticket->resolution }}</dd>@endif
        </dl>
    </s-section>

    <s-section heading="Conversation">
        <ol class="sp-thread">
            @foreach ($messages as $m)
                <li class="sp-{{ $m->author }}">
                    <div class="sp-meta"><strong>{{ $m->author === 'team' ? 'OrderOrbit Space team'.($m->author_name ? ' · '.$m->author_name : '') : ($m->author_name ?: 'You') }}</strong> <span class="oo-muted oo-small">{{ $m->created_at->toDayDateTimeString() }}</span></div>
                    <div class="sp-body">{!! nl2br(e($m->body)) !!}</div>
                    @foreach ($m->attachments as $a)<div class="oo-small"><s-link href="{{ app_route('app.support.attachment', ['attachment' => $a->id]) }}" target="_blank">📎 {{ $a->filename }}</s-link> <span class="oo-muted">{{ round($a->size / 1024) }} KB</span></div>@endforeach
                </li>
            @endforeach
        </ol>
    </s-section>

    <s-section heading="{{ $ticket->status === 'closed' ? 'Reopen with a reply' : 'Reply' }}">
        <form method="POST" action="{{ app_route('app.support.reply', ['ticket' => $ticket->id]) }}" enctype="multipart/form-data" class="sp-form">
            <textarea name="body" rows="4" maxlength="10000" required aria-label="Your reply"></textarea>
            <input type="file" name="attachments[]" multiple accept="image/*,application/pdf,text/plain,text/csv" aria-label="Attachments">
            <div class="oo-inline"><s-button type="submit" variant="primary">Send reply</s-button></div>
        </form>
        <form method="POST" action="{{ app_route('app.support.close', ['ticket' => $ticket->id]) }}" style="margin-top:12px">
            <s-button type="submit" variant="tertiary">{{ $ticket->status === 'closed' ? 'Reopen ticket' : 'Close ticket: my issue is solved' }}</s-button>
        </form>
    </s-section>
</s-page>
@endsection

@push('head')
    <style>
        .sp-form { display: grid; gap: 10px; max-width: 720px; } .sp-form textarea { font: inherit; padding: 8px 10px; border: 1px solid #8a8a8a; border-radius: 8px; }
        .sp-thread { display: grid; gap: 12px; margin: 0; padding: 0; list-style: none; }
        .sp-thread li { padding: 12px 14px; border-radius: 10px; border: 1px solid #e3e3e3; }
        .sp-thread li.sp-team { background: #f7f6ff; border-color: #e1ddff; }
        .sp-meta { margin-bottom: 6px; } .sp-body { white-space: normal; line-height: 1.55; }
    </style>
@endpush
