@extends('admin.layout')
@section('title', 'Contact inbox')
@section('content')
@php use App\Models\ContactSubmission; @endphp
<div class="ad-head"><div><h1>Contact inbox</h1><p>Messages from the website contact form. Each new one is also emailed to the team (Email providers › Team alerts go to).</p></div></div>
<nav class="ad-tabs">
    @foreach (ContactSubmission::STATUSES + ['all' => 'All'] as $k => $l)
        <a href="{{ route('admin.contact', ['status' => $k, 'q' => $q ?: null]) }}" @if ($status === $k) aria-current="page" @endif>{{ $l }} @if ($k !== 'all')<span>{{ $counts[$k] ?? 0 }}</span>@endif</a>
    @endforeach
</nav>
<form method="GET" action="{{ route('admin.contact') }}" class="ad-form" style="max-width:520px;margin-bottom:12px">
    <input type="hidden" name="status" value="{{ $status }}">
    <label>Search<input type="search" name="q" value="{{ $q }}" placeholder="Name, email, store or text"></label>
</form>
<section class="ad-card flush"><div class="ad-scroll">
<table class="ad-table">
    <thead><tr><th>From</th><th>Topic</th><th>Message</th><th>Store</th><th>Status</th><th>Received</th></tr></thead>
    <tbody>
        @forelse ($messages as $m)
            <tr>
                <td><a href="{{ route('admin.contact.show', $m->id) }}"><b>{{ $m->name }}</b></a><br><span class="ad-muted">{{ $m->email }}</span></td>
                <td>{{ $m->topic }}</td>
                <td>{{ \Illuminate\Support\Str::limit($m->message, 90) }}</td>
                <td>@if ($m->store)<a href="{{ route('admin.store', $m->store_id) }}">{{ $m->store->shop_domain }}</a>@else <span class="ad-muted">{{ $m->store_url ?: '—' }}</span>@endif</td>
                <td><span class="ad-badge {{ $m->status === 'new' ? 'bad' : ($m->status === 'replied' ? 'ok' : '') }}">{{ ContactSubmission::STATUSES[$m->status] ?? $m->status }}</span></td>
                <td>{{ $m->created_at->diffForHumans() }}</td>
            </tr>
        @empty <tr><td colspan="6" class="ad-muted">No messages.</td></tr> @endforelse
    </tbody>
</table>
</div></section>
{{ $messages->links('admin._pager') }}
@endsection
