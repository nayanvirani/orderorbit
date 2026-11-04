@extends('admin.layout')
@section('title', $ticket->reference())
@section('content')
@php use App\Models\Support\Ticket; @endphp
<p class="ad-crumbs"><a href="{{ route('admin.tickets') }}">Support desk</a> / {{ $ticket->reference() }}</p>
<div class="ad-head"><div><h1>{{ $ticket->subject }}</h1><p>{{ $ticket->reference() }} · <a href="{{ route('admin.store', $ticket->store_id) }}">{{ $ticket->store->shop_domain }}</a> · <span class="ad-badge">{{ Ticket::STATUSES[$ticket->status] ?? $ticket->status }}</span></p></div></div>
<div class="ad-grid-2">
    <section class="ad-card ad-thread">
        <h2>Conversation</h2>
        @foreach ($ticket->messages as $m)
            <div class="ad-msg {{ $m->author }} {{ $m->internal ? 'internal' : '' }}">
                <div class="ad-muted"><b>{{ $m->internal ? 'Internal note' : ($m->author === 'team' ? 'Team' : 'Merchant') }}</b> · {{ $m->author_name }} · {{ $m->created_at->toDayDateTimeString() }}</div>
                <div>{!! nl2br(e($m->body)) !!}</div>
                @foreach ($m->attachments as $a)<div><a href="{{ route('admin.attachment', $a->id) }}" target="_blank">📎 {{ $a->filename }}</a> <span class="ad-muted">{{ round($a->size / 1024) }} KB</span></div>@endforeach
            </div>
        @endforeach
        @if ($customPlan)
            @php($cp = $customPlan)
            <div class="ad-card" style="margin:12px 0;padding:14px">
                <div class="ad-head" style="margin:0 0 6px"><div><h2 style="margin:0">Custom plan request</h2>
                    <p class="ad-muted" style="margin:4px 0 0">On {{ $cp['plan'] }}{{ $cp['planPrice'] !== null ? ' ($'.number_format($cp['planPrice'], 2).')' : '' }} · {{ $cp['currency'] }} {{ number_format($cp['sales'], 2) }} sales in the last 30 days ({{ number_format($cp['orders']) }} orders)
                    @if ($cp['widgets'] !== null) · wants {{ $cp['widgets'] }} live widgets (plan: {{ $cp['widgetsLimit'] ?? 'unlimited' }}) @endif
                    @if ($cp['runs'] !== null) · {{ number_format($cp['runs']) }} runs a month (plan: {{ $cp['runsLimit'] !== null ? number_format($cp['runsLimit']) : 'unlimited' }}) @endif
                    @if ($cp['budget'] !== null) · budget ${{ $cp['budget'] }}/month @endif</p></div>
                    @if (! in_array($ticket->status, ['closed', 'resolved'], true))
                        <form method="POST" action="{{ route('admin.ticket.update', $ticket->id) }}">@csrf<input type="hidden" name="status" value="closed"><input type="hidden" name="priority" value="{{ $ticket->priority }}"><input type="hidden" name="assigned_to" value="{{ $ticket->assigned_to }}"><input type="hidden" name="resolution" value="{{ $ticket->resolution }}"><button class="ad-btn" type="submit">Close request</button></form>
                    @endif
                </div>
                <p style="margin:8px 0;padding:10px 12px;border-radius:10px;background:#f4f2ff"><b>Suggested answer:</b> {{ $cp['suggestion'] }}</p>
                <table class="ad-table"><thead><tr><th>What they asked for</th><th>Status</th></tr></thead><tbody>
                    @foreach ($cp['rows'] as $r)<tr><td>{{ $r['label'] }}</td><td>
                        @if ($r['coming'])<span class="ad-badge">Coming soon</span> <span class="ad-muted">{{ $r['needs'] }} when it ships</span>
                        @elseif ($r['has'])<span class="ad-badge ok">Included in their plan</span>{{ $r['on'] ? ' · turned on for this store' : '' }}
                        @else<span class="ad-badge warn">Needs {{ $r['needs'] }}</span>@endif
                    </td></tr>@endforeach
                </tbody></table>
                <p style="margin:12px 0 4px"><b>Reply templates</b> <span class="ad-muted">(fill the reply box below; edit before sending)</span></p>
                @foreach ($cp['templates'] as $label => $text)
                    <button type="button" class="ad-btn" data-template="{{ $text }}" onclick="document.querySelector('textarea[name=body]').value = this.dataset.template; var m = document.querySelector('textarea[name=message]'); if (m) m.value = this.dataset.template;">{{ $label }}</button>
                @endforeach

                <form method="POST" action="{{ route('admin.ticket.custom-plan', $ticket->id) }}" class="ad-form" style="margin-top:14px;border-top:1px solid #ecebf3;padding-top:12px">
                    @csrf
                    <h3 style="margin:0">Store access</h3>
                    <p class="ad-muted" style="margin:2px 0 8px">Turn the requested modules on for this store only. Unticked ones follow their plan. Changes take effect at once and are in the audit log.</p>
                    @foreach ($cp['rows'] as $r)
                        @continue($r['coming'])
                        <label class="ad-check"><input type="checkbox" name="modules[]" value="{{ $r['key'] }}" @checked($r['has'])> {{ $r['label'] }} <span class="ad-muted">{{ $r['has'] && ! $r['on'] ? '(in their plan)' : ($r['on'] ? '(turned on for this store)' : '(needs '.$r['needs'].')') }}</span></label>
                    @endforeach
                    <div class="ad-fields">
                        <label>Live widgets<input type="number" name="widgets" min="0" placeholder="{{ $cp['widgetsLimit'] ?? 'Unlimited' }} (plan)" value="{{ $cp['entitlements']['limits']['active_experiences'] ?? '' }}"></label>
                        <label>Automation runs a month<input type="number" name="runs" min="0" placeholder="{{ $cp['runsLimit'] ?? 'Unlimited' }} (plan)" value="{{ $cp['entitlements']['limits']['automation_executions'] ?? '' }}"></label>
                        <label>Plan override<select name="plan"><option value="">None (their plan)</option>@foreach ($cp['plans'] as $p)<option value="{{ $p['key'] }}" @selected(($cp['entitlements']['plan'] ?? '') === $p['key'])>{{ $p['name'] }} (${{ number_format($p['price'], 2) }})</option>@endforeach</select></label>
                        <label>Until<input type="date" name="plan_until" min="{{ now()->toDateString() }}" value="{{ $cp['entitlements']['plan_until'] ?? '' }}"></label>
                    </div>
                    <label>Note (shown in the audit log)<input type="text" name="note" maxlength="1000" value=""></label>
                    <label>Message to the store owner (optional; sent with the change)<textarea name="message" rows="4"></textarea></label>
                    <button class="ad-btn primary" type="submit">Apply and tell the store owner</button>
                </form>
            </div>
        @endif
        @include('admin._suggestions', ['suggestUrl' => route('admin.ticket', ['ticket' => $ticket->id, 'suggest' => 1]), 'suggestions' => $suggestions])
        <form method="POST" action="{{ route('admin.ticket.reply', $ticket->id) }}" enctype="multipart/form-data" class="ad-form">
            @csrf
            <input type="hidden" name="saved_reply_id" value="">
            <label>Reply<textarea name="body" rows="5" required></textarea></label>
            <input type="file" name="attachments[]" multiple>
            <label class="ad-check"><input type="checkbox" name="internal" value="1"> Internal note (the merchant doesn't see it)</label>
            <button class="ad-btn primary" type="submit">Send</button>
        </form>
    </section>
    <section class="ad-card">
        <h2>Ticket</h2>
        <form method="POST" action="{{ route('admin.ticket.update', $ticket->id) }}" class="ad-form">
            @csrf
            <label>Status<select name="status">@foreach (Ticket::STATUSES as $k => $l)<option value="{{ $k }}" @selected($ticket->status === $k)>{{ $l }}</option>@endforeach</select></label>
            <label>Priority<select name="priority">@foreach (Ticket::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected($ticket->priority === $k)>{{ $l }}</option>@endforeach</select></label>
            <label>Assigned to<select name="assigned_to"><option value="">Nobody</option>@foreach ($team as $u)<option value="{{ $u->id }}" @selected($ticket->assigned_to === $u->id)>{{ $u->name }} ({{ $u->email }})</option>@endforeach</select></label>
            <label>Resolution (shown to the merchant)<textarea name="resolution" rows="3">{{ $ticket->resolution }}</textarea></label>
            <button class="ad-btn primary" type="submit">Update ticket</button>
        </form>
        <h2 style="margin-top:18px">Store</h2>
        <p><a href="{{ route('admin.store', $ticket->store_id) }}">{{ $ticket->store->shop_domain }}</a><br><span class="ad-muted">Opened by {{ $ticket->requester?->email ?? 'a staff member' }} · {{ Ticket::CATEGORIES[$ticket->category] ?? $ticket->category }}</span></p>
        <h2 style="margin-top:18px">Diagnostics at opening</h2>
        <pre class="ad-pre">{{ json_encode($ticket->diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </section>
</div>
@endsection
