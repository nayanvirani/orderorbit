@extends('admin.layout')
@section('title', 'Feature flags')
@section('content')
<h1>Feature flags</h1>
<p class="ad-muted">Turn features on for every store, or only for listed stores (for betas). Code checks them with <code>Features::enabled('key', $store)</code>.</p>
<table class="ad-table">
    <thead><tr><th>Key</th><th>Description</th><th>Everyone</th><th>Only these stores</th><th></th></tr></thead>
    <tbody>
        @forelse ($flags as $f)
            <tr>
                <td><code>{{ $f->key }}</code></td><td>{{ $f->description }}</td><td>{!! $f->enabled ? '<span class="ad-ok">On</span>' : 'Off' !!}</td>
                <td>{{ \App\Models\Store::whereIn('id', $f->store_ids ?? [])->pluck('shop_domain')->implode(', ') ?: '—' }}</td>
                <td><form method="POST" action="{{ route('admin.flags.delete', $f->id) }}" onsubmit="return confirm('Delete this flag?')">@csrf<button class="ad-btn" type="submit">Delete</button></form></td>
            </tr>
        @empty <tr><td colspan="5" class="ad-muted">No flags yet.</td></tr> @endforelse
    </tbody>
</table>
<section class="ad-card">
    <h2>Add or update a flag</h2>
    <form method="POST" action="{{ route('admin.flags.save') }}" class="ad-form">
        @csrf
        <label>Key<input name="key" required pattern="[a-z0-9_.\-]{2,60}" placeholder="email_sending"></label>
        <label>Description<input name="description" maxlength="300"></label>
        <label>Only these stores (shop domains, one per line or comma-separated)<textarea name="store_domains" rows="3"></textarea></label>
        <label class="ad-check"><input type="checkbox" name="enabled" value="1"> On for every store</label>
        @if ($errors->any())<p class="ad-error">{{ $errors->first() }}</p>@endif
        <button class="ad-btn primary" type="submit">Save flag</button>
    </form>
</section>
@endsection
