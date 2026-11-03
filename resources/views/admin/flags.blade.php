@extends('admin.layout')
@section('title', 'Feature flags')
@section('content')
@php use App\Models\FeatureFlag; use App\Support\Features; @endphp
<div class="ad-head"><div><h1>Feature flags</h1><p>Switches the code checks before showing a feature. Release a new module to your test store, then some stores, then everyone. Flags are defined in the code, so you pick them here; keys can't be typed in.</p></div></div>
@if ($errors->any())<div class="ad-flash bad">{{ $errors->first() }}</div>@endif
<div class="ad-grid-2">
    <section class="ad-card flush"><div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>Feature</th><th>Who gets it</th><th>Schedule</th></tr></thead>
            <tbody>
                @foreach (array_filter($registry, fn ($f) => ! $f['kill']) as $k => $f)
                    @php($r = $rows[$k] ?? null)
                    <tr @if ($k === $key) style="background:var(--accent-soft, #f3f1ff)" @endif>
                        <td><a href="{{ route('admin.flags', ['key' => $k]) }}"><b>{{ $f['name'] }}</b></a><br><span class="ad-muted"><code>{{ $k }}</code> · {{ $f['area'] }} · {{ $f['phase'] }}@unless ($f['built']) · not built yet @endunless</span></td>
                        <td>
                            @switch($r?->mode ?? 'off')
                                @case('all') <span class="ad-badge ok">Everyone</span> @break
                                @case('stores') <span class="ad-badge">{{ count($r->store_ids ?? []) }} {{ \Illuminate\Support\Str::plural('store', count($r->store_ids ?? [])) }}</span> @break
                                @case('percent') <span class="ad-badge">{{ $r->percent }}% of stores</span> @break
                                @default <span class="ad-badge">Off</span>
                            @endswitch
                        </td>
                        <td class="ad-muted">@if ($r?->starts_at)On {{ $r->starts_at->format('j M, H:i') }}@endif @if ($r?->ends_at)· off {{ $r->ends_at->format('j M, H:i') }}@endif @if (! $r?->starts_at && ! $r?->ends_at)—@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div></section>
    <section class="ad-card">
        @php($f = $registry[$key])
        <h2>{{ $f['name'] }}</h2>
        <p class="ad-muted">{{ $f['description'] }} <code>{{ $key }}</code>@unless ($f['built'])<br><b>Not in the app yet:</b> set it up now; it takes effect when this module ships.@endunless</p>
        <form method="POST" action="{{ route('admin.flags.save') }}" class="ad-form">
            @csrf
            <input type="hidden" name="key" value="{{ $key }}">
            @php($mode = old('mode', $flag?->mode ?? 'off'))
            <label>Who gets it
                <select name="mode">@foreach (FeatureFlag::MODES as $m => $l)<option value="{{ $m }}" @selected($mode === $m)>{{ $l }}</option>@endforeach</select>
                <small>"Everyone" still follows each store's plan.</small>
            </label>
            @php($chosen = array_map('intval', old('store_ids', $flag?->store_ids ?? [])))
            <label>Chosen stores (for "Only chosen stores")
                <select name="store_ids[]" multiple size="6">@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(in_array($s->id, $chosen, true))>{{ $s->shop_domain }}</option>@endforeach</select>
                <small>Hold Ctrl (Cmd on a Mac) to choose several.</small>
            </label>
            <label>Share of stores (for "A share of stores")
                <select name="percent"><option value="">—</option>@foreach ([5, 10, 25, 50, 75] as $p)<option value="{{ $p }}" @selected((int) old('percent', $flag?->percent) === $p)>{{ $p }}%</option>@endforeach</select>
                <small>The same stores stay in as you raise it.</small>
            </label>
            <label>Turn on at (optional)<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $flag?->starts_at?->format('Y-m-d\TH:i')) }}"><small>Times are UTC. Empty: now.</small></label>
            <label>Turn off at (optional)<input type="datetime-local" name="ends_at" value="{{ old('ends_at', $flag?->ends_at?->format('Y-m-d\TH:i')) }}"><small>Empty: stays on.</small></label>
            <button class="ad-btn primary" type="submit">Save</button>
        </form>
    </section>
</div>

<section class="ad-card">
    <h2>Emergency off</h2>
    <p class="ad-muted">Hide a live part of Growvia for every store at once during an incident. Settings and data are kept; turning it back on restores everything. Every store is updated straight away (and again within 5 minutes).</p>
    <table class="ad-table">
        <tbody>
            @foreach (array_filter($registry, fn ($f) => $f['kill']) as $k => $f)
                @php($on = Features::enabled($k))
                <tr>
                    <td><b>{{ $f['name'] }}</b><br><span class="ad-muted">{{ $f['description'] }}</span></td>
                    <td>@if ($on)<span class="ad-badge bad">OFF for every store</span>@else<span class="ad-badge ok">Normal</span>@endif</td>
                    <td><form method="POST" action="{{ route('admin.flags.kill', $k) }}" onsubmit="return confirm('{{ $on ? 'Turn '.$f['name'].' back on for every store?' : 'Turn OFF '.$f['name'].' for every store?' }}')">@csrf<input type="hidden" name="on" value="{{ $on ? 0 : 1 }}"><button class="ad-btn small {{ $on ? '' : 'danger' }}" type="submit">{{ $on ? 'Turn back on' : 'Turn off for everyone' }}</button></form></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>

<section class="ad-card">
    <h2>History</h2>
    @forelse ($history as $h)
        @php($c = $h->context ?? [])
        <p style="margin:6px 0"><b>{{ $registry[$c['flag'] ?? '']['name'] ?? ($c['flag'] ?? '?') }}</b> ·
            @if ($h->action === 'admin.kill_switch'){{ ($c['on'] ?? false) ? 'turned off for every store' : 'turned back on' }}@else{{ FeatureFlag::MODES[$c['mode'] ?? 'off'] ?? '' }}@if (($c['mode'] ?? '') === 'percent') {{ $c['percent'] }}%@elseif (($c['mode'] ?? '') === 'stores') ({{ $c['stores'] }})@endif @endif
            <span class="ad-muted">· {{ $c['by'] ?? '' }} · {{ $h->created_at->diffForHumans() }}</span></p>
    @empty <p class="ad-muted">No changes yet.</p> @endforelse
</section>

@if ($unknown->isNotEmpty())
    <section class="ad-card">
        <h2>Old flags the code doesn't check</h2>
        @foreach ($unknown as $u)
            <form method="POST" action="{{ route('admin.flags.delete', $u->id) }}" style="display:flex;gap:10px;align-items:center;margin:6px 0">@csrf<code>{{ $u->key }}</code><button class="ad-btn small danger" type="submit">Delete</button></form>
        @endforeach
    </section>
@endif
@endsection
