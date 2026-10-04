@extends('layouts.embedded')

@section('title', 'Support')

@php
    use App\Models\Support\Ticket;
    $old = $old ?? [];
    $tone = ['open' => 'info', 'pending' => 'warning', 'resolved' => 'success', 'closed' => 'neutral'];
@endphp

@section('content')
<s-page inlineSize="large" heading="Support">
    <x-app.hero eyebrow="Support" icon="message" tone="analytics" title="We're here to <em>help.</em>"
        lead="Open a ticket and the OrderOrbit Space team replies here, usually within one business day. Your store's setup details are attached automatically, so you don't need to explain them.">
        <s-button href="{{ route('site.help') }}" target="_blank">Help center</s-button>
        <s-button href="{{ route('site.docs.index') }}" target="_blank" variant="tertiary">Documentation</s-button>
    </x-app.hero>

    <s-section heading="Your tickets">
        @if ($tickets->isEmpty())
            <s-paragraph><span class="oo-muted">No tickets yet.</span></s-paragraph>
        @else
            <table class="oo-table stack">
                <thead><tr><th>Ticket</th><th>Subject</th><th>Category</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                    @foreach ($tickets as $t)
                        <tr>
                            <td data-label="Ticket"><s-link href="{{ app_route('app.support.show', ['ticket' => $t->id]) }}">{{ $t->reference() }}</s-link></td>
                            <td data-label="Subject">{{ $t->subject }}@if ($t->status === 'pending') <s-badge tone="warning">New reply</s-badge>@endif</td>
                            <td data-label="Category">{{ Ticket::CATEGORIES[$t->category] ?? $t->category }}</td>
                            <td data-label="Status"><s-badge tone="{{ $tone[$t->status] }}">{{ Ticket::STATUSES[$t->status] }}</s-badge></td>
                            <td data-label="Updated">{{ $t->updated_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </s-section>

    <s-section heading="Open a ticket">
        <form method="POST" action="{{ app_route('app.support.store') }}" enctype="multipart/form-data" class="sp-form">
            <div class="oo-form-row">
                <label class="oo-field">Category
                    <select name="category" required>@foreach (Ticket::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(($old['category'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
                </label>
                <label class="oo-field">Priority
                    <select name="priority">@foreach (Ticket::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected(($old['priority'] ?? 'normal') === $k)>{{ $l }}</option>@endforeach</select>
                </label>
            </div>
            <label class="oo-field">Subject<input type="text" name="subject" maxlength="160" required value="{{ $old['subject'] ?? '' }}" placeholder="The bundle doesn't show on my product page"></label>
            @isset($errors['subject'])<p class="b-error">{{ $errors['subject'][0] }}</p>@endisset
            <label class="oo-field">What's happening?<textarea name="body" rows="6" maxlength="10000" required placeholder="What did you expect, what happened instead, and which page or experience is it on?">{{ $old['body'] ?? '' }}</textarea></label>
            @isset($errors['body'])<p class="b-error">{{ $errors['body'][0] }}</p>@endisset
            <label class="oo-field">Attachments (optional, up to 3 files, 4 MB each)<input type="file" name="attachments[]" multiple accept="image/*,application/pdf,text/plain,text/csv"></label>
            @foreach (collect($errors)->filter(fn ($v, $k) => str_starts_with($k, 'attachments')) as $messages)<p class="b-error">{{ $messages[0] }}</p>@endforeach
            <div><s-button type="submit" variant="primary">Send ticket</s-button></div>
        </form>
    </s-section>
</s-page>
@endsection

@push('head')
    <style>.sp-form { display: grid; gap: 12px; max-width: 720px; } .sp-form textarea { font: inherit; padding: 8px 10px; border: 1px solid #8a8a8a; border-radius: 8px; }</style>
@endpush
