@extends('admin.layout')
@section('title', 'Announcements')
@section('content')
@php use App\Models\Announcement; @endphp
<div class="ad-head"><div><h1>Announcements</h1><p>Messages to merchants inside Growvia, shown between their start and end time to the stores you choose. Use them for new features, maintenance, Shopify issues and plan price changes (30 days' notice).</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">@foreach (['feature' => 'New feature', 'maintenance' => 'Maintenance', 'shopify' => 'Shopify issue', 'price' => 'Price change'] as $k => $l)<a class="ad-btn {{ $loop->first ? 'primary' : '' }}" href="{{ route('admin.announcements.new', ['template' => $k]) }}">+ {{ $l }}</a>@endforeach</div></div>
<section class="ad-card flush"><div class="ad-scroll">
<table class="ad-table">
    <thead><tr><th>Announcement</th><th>Who sees it</th><th>Dates (UTC)</th><th>Status</th><th>Seen</th><th>Clicks</th></tr></thead>
    <tbody>
        @forelse ($announcements as $a)
            <tr>
                <td><a href="{{ route('admin.announcements.edit', $a->id) }}"><b>{{ $a->title }}</b></a><br><span class="ad-muted">{{ Announcement::PLACEMENTS[$a->placement] }}@if ($a->price_change_at) · price change @endif @if ($a->email_owners) · emails owners{{ $a->emailed_at ? ' (sent)' : '' }}@endif</span></td>
                <td>{{ Announcement::AUDIENCES[$a->audience] }}@if ($a->audience === 'plans'): {{ implode(', ', $a->plans ?? []) }}@elseif ($a->audience === 'module'): {{ $a->module }}@elseif ($a->audience === 'stores'): {{ count($a->store_ids ?? []) }}@endif @if ($a->owners_only)<br><span class="ad-muted">owners only</span>@endif</td>
                <td>{{ $a->starts_at->format('j M H:i') }} → {{ $a->ends_at?->format('j M H:i') ?? 'no end' }}</td>
                <td><span class="ad-badge {{ $a->status() === 'Live' ? 'ok' : '' }}">{{ $a->status() }}</span></td>
                <td><a href="{{ route('admin.announcements.show', $a->id) }}">{{ $a->views_count }}</a>@if ($a->dismissed_count)<br><span class="ad-muted">{{ $a->dismissed_count }} closed</span>@endif</td>
                <td>{{ $a->clicks }}</td>
            </tr>
        @empty <tr><td colspan="6" class="ad-muted">No announcements yet. Start from a template above.</td></tr> @endforelse
    </tbody>
</table>
</div></section>
@endsection
