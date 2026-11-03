@extends('admin.layout')
@section('title', 'Who saw it')
@section('content')
<p class="ad-crumbs"><a href="{{ route('admin.announcements') }}">Announcements</a> / {{ $a->title }}</p>
<div class="ad-head"><div><h1>Who saw "{{ $a->title }}"</h1><p>{{ $views->total() }} people · {{ $a->clicks }} button clicks@if ($a->price_change_at) · price change notice record@endif</p></div></div>
<section class="ad-card flush"><div class="ad-scroll">
<table class="ad-table">
    <thead><tr><th>Store</th><th>Person</th><th>First seen (UTC)</th><th>Closed</th></tr></thead>
    <tbody>
        @forelse ($views as $v)
            <tr><td>{{ $v->store?->shop_domain }}</td><td>{{ trim(($v->user?->first_name ?? '').' '.($v->user?->last_name ?? '')) ?: '—' }} <span class="ad-muted">{{ $v->user?->email }} · {{ $v->user?->role }}</span></td><td>{{ $v->seen_at->format('j M Y H:i') }}</td><td>{{ $v->dismissed_at?->format('j M H:i') ?? '—' }}</td></tr>
        @empty <tr><td colspan="4" class="ad-muted">Nobody yet.</td></tr> @endforelse
    </tbody>
</table>
</div></section>
{{ $views->links('admin._pager') }}
@endsection
