@extends('admin.layout')
@section('title', 'Support')
@section('content')
<h1>Support desk</h1>
<nav class="ad-tabs">
    @foreach (['open' => 'Open', 'pending' => 'Waiting for merchant', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'] as $k => $l)
        <a href="{{ route('admin.tickets', ['status' => $k]) }}" @if ($status === $k) aria-current="page" @endif>{{ $l }} @if ($k !== 'all')({{ $counts[$k] ?? 0 }})@endif</a>
    @endforeach
</nav>
<table class="ad-table">
    <thead><tr><th>Ticket</th><th>Store</th><th>Category</th><th>Priority</th><th>Assigned</th><th>Last reply</th></tr></thead>
    <tbody>
        @forelse ($tickets as $t)
            <tr>
                <td><a href="{{ route('admin.ticket', $t->id) }}">{{ $t->reference() }} · {{ $t->subject }}</a></td>
                <td>{{ $t->store->shop_domain }}</td>
                <td>{{ \App\Models\Support\Ticket::CATEGORIES[$t->category] ?? $t->category }}</td>
                <td class="{{ in_array($t->priority, ['urgent', 'high'], true) ? 'ad-bad' : '' }}">{{ ucfirst($t->priority) }}</td>
                <td>{{ $t->assignee?->name ?? '—' }}</td>
                <td>{{ $t->last_reply_at?->diffForHumans() }} · {{ $t->last_reply_by === 'team' ? 'us' : 'merchant' }}</td>
            </tr>
        @empty <tr><td colspan="6" class="ad-muted">No tickets.</td></tr> @endforelse
    </tbody>
</table>
{{ $tickets->links() }}
@endsection
