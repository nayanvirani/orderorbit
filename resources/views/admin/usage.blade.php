@extends('admin.layout')
@section('title', 'Usage insights')
@section('content')
<div class="ad-head"><div><h1>Usage insights</h1><p>Installs, activation, feature use and why stores leave. Every number counts stores from the store list.</p></div>
    <div class="ad-actions">
        @foreach ($ranges as $k => $label)<a class="ad-btn {{ $range === $k ? 'primary' : '' }}" href="{{ route('admin.usage', ['range' => $k]) }}">{{ $label }}</a>@endforeach
        <a class="ad-btn" href="{{ route('admin.usage.export') }}">Export CSV</a>
    </div>
</div>
<div class="ad-kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px">
    @foreach ($kpis as [$label, $value, $note])
        <section class="ad-card" style="margin:0"><div class="ad-muted">{{ $label }}</div><div style="font-size:26px;font-weight:700;margin:4px 0">{{ is_numeric($value) ? number_format($value) : $value }}</div><div class="ad-muted" style="font-size:12px">{{ $note }}</div></section>
    @endforeach
</div>
<div class="ad-grid-2">
    <section class="ad-card">
        <h2>Activation funnel</h2>
        <p class="ad-muted">Stores installed in this range, and how far they got.</p>
        @foreach ($funnel as $f)
            <div style="margin:10px 0">
                <div style="display:flex;justify-content:space-between;font-size:14px"><span>{{ $f['label'] }}</span><span><b>{{ number_format($f['count']) }}</b> · {{ $f['pct'] }}%</span></div>
                <div style="height:8px;border-radius:4px;background:#ecebf3;overflow:hidden;margin-top:4px"><i style="display:block;height:100%;width:{{ $f['pct'] }}%;background:#5b45f0"></i></div>
            </div>
        @endforeach
    </section>
    <section class="ad-card">
        <h2>Plan mix</h2>
        <p class="ad-muted">{{ number_format($activeCount) }} stores with the app now.</p>
        <table class="ad-table"><tbody>
            @foreach ($mix as $m)<tr><td>{{ $m['plan'] }}</td><td class="num">{{ number_format($m['n']) }} stores</td><td class="num">{{ $activeCount ? round($m['n'] / $activeCount * 100) : 0 }}%</td></tr>@endforeach
        </tbody></table>
    </section>
</div>
<section class="ad-card flush"><div class="ad-scroll">
    <table class="ad-table">
        <thead><tr><th>Feature</th><th class="num">Stores using</th><th class="num">Of stores with the app</th><th class="num">Started in range</th></tr></thead>
        <tbody>
            @foreach ($features as $f)<tr><td>{{ $f['name'] }}</td><td class="num">{{ number_format($f['n']) }}</td><td class="num">{{ $f['p'] }}%</td><td class="num">{{ number_format($f['new']) }}</td></tr>@endforeach
        </tbody>
    </table>
</div></section>
<section class="ad-card">
    <h2>Why stores uninstalled</h2>
    <p class="ad-muted">From the optional question in the goodbye email.</p>
    @forelse ($reasons as $r)
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid #f1f0f6"><span>{{ $r['r'] }}</span><b>{{ $r['n'] }}</b></div>
    @empty <p class="ad-muted">No answers yet.</p> @endforelse
    @if ($notes->isNotEmpty())
        <h3 style="margin-top:14px">Latest notes</h3>
        @foreach ($notes as $n)<p style="margin:6px 0"><span class="ad-muted">{{ \App\Http\Controllers\GoodbyeController::REASONS[$n->reason] ?? $n->reason }} · {{ \Carbon\Carbon::parse($n->updated_at)->diffForHumans() }}</span><br>{{ $n->note }}</p>@endforeach
    @endif
</section>
@endsection
