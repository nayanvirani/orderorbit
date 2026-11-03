@extends('admin.layout')
@section('title', $a ? 'Edit announcement' : 'New announcement')
@section('content')
@php use App\Models\Announcement; $price = old('price_change', $a ? (bool) $a->price_change_at : $templateKey === 'price'); @endphp
<p class="ad-crumbs"><a href="{{ route('admin.announcements') }}">Announcements</a> / {{ $a ? $a->title : 'New' }}</p>
<div class="ad-head"><div><h1>{{ $a ? 'Edit announcement' : 'New announcement' }}</h1><p>Replace the {braces} in a template with your own words.</p></div></div>
@if ($errors->any())<div class="ad-flash bad">{{ $errors->first() }}</div>@endif
<section class="ad-card">
<form method="POST" action="{{ $a ? route('admin.announcements.update', $a->id) : route('admin.announcements.store') }}" class="ad-form">
    @csrf
    <label class="ad-check"><input type="checkbox" name="price_change" value="1" @checked($price)> This is a plan price change <small>(must start at least 30 days before the new price, can't be closed, emails every owner, keeps a record of who saw it)</small></label>
    <label>Title<input name="title" required maxlength="160" value="{{ old('title', $a?->title ?? $template[0]) }}"></label>
    <label>Message<textarea name="body" rows="4" required maxlength="2000">{{ old('body', $a?->body ?? $template[1]) }}</textarea></label>
    <div class="ad-grid-2">
        <label>Button text (optional)<input name="cta_text" maxlength="60" value="{{ old('cta_text', $a?->cta_text ?? $template[2]) }}"></label>
        <label>Button link<input name="cta_url" maxlength="500" value="{{ old('cta_url', $a?->cta_url) }}" placeholder="/app/settings/billing or https://…"></label>
    </div>
    <div class="ad-grid-2">
        <label>Style<select name="tone">@foreach (Announcement::TONES as $k => $l)<option value="{{ $k }}" @selected(old('tone', $a?->tone ?? $template[3]) === $k)>{{ $l }}</option>@endforeach</select></label>
        <label>Where<select name="placement">@foreach (Announcement::PLACEMENTS as $k => $l)<option value="{{ $k }}" @selected(old('placement', $a?->placement ?? 'home') === $k)>{{ $l }}</option>@endforeach</select></label>
    </div>
    <label>Who sees it<select name="audience">@foreach (Announcement::AUDIENCES as $k => $l)<option value="{{ $k }}" @selected(old('audience', $a?->audience ?? 'all') === $k)>{{ $l }}</option>@endforeach</select></label>
    <div class="ad-grid-2">
        <label>Plans (for "chosen plans")<select name="plans[]" multiple size="5">@foreach ($plans as $k => $l)<option value="{{ $k }}" @selected(in_array($k, old('plans', $a?->plans ?? []), true))>{{ $l }}</option>@endforeach</select></label>
        <label>Module (for "plan includes a module")<select name="module"><option value="">—</option>@foreach ($modules as $k => $l)<option value="{{ $k }}" @selected(old('module', $a?->module) === $k)>{{ $l }}</option>@endforeach</select></label>
    </div>
    <label>Stores (for "chosen stores")<select name="store_ids[]" multiple size="5">@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(in_array($s->id, array_map('intval', old('store_ids', $a?->store_ids ?? [])), true))>{{ $s->shop_domain }}</option>@endforeach</select></label>
    <label class="ad-check"><input type="checkbox" name="owners_only" value="1" @checked(old('owners_only', $a?->owners_only))> Only store owners (not admins and staff)</label>
    <div class="ad-grid-2">
        <label>Start (UTC)<input type="datetime-local" name="starts_at" required value="{{ old('starts_at', ($a?->starts_at ?? now())->format('Y-m-d\TH:i')) }}"></label>
        <label>End (UTC, optional)<input type="datetime-local" name="ends_at" value="{{ old('ends_at', $a?->ends_at?->format('Y-m-d\TH:i')) }}"></label>
    </div>
    <label>New price applies on (price change only)<input type="datetime-local" name="price_change_at" value="{{ old('price_change_at', $a?->price_change_at?->format('Y-m-d\TH:i')) }}"><small>The announcement ends on this date.</small></label>
    <label class="ad-check"><input type="checkbox" name="dismissible" value="1" @checked(old('dismissible', $a?->dismissible ?? true))> Merchants can close it</label>
    <label class="ad-check"><input type="checkbox" name="email_owners" value="1" @checked(old('email_owners', $a?->email_owners))> Also email store owners when it starts</label>
    <div style="display:flex;gap:8px"><button class="ad-btn primary" type="submit">Save</button><a class="ad-btn" href="{{ route('admin.announcements') }}">Cancel</a></div>
</form>
@if ($a)
    <form method="POST" action="{{ route('admin.announcements.delete', $a->id) }}" onsubmit="return confirm('Delete this announcement?')" style="margin-top:12px">@csrf<button class="ad-btn small danger" type="submit">Delete</button></form>
@endif
</section>
@endsection
