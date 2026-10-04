@extends('layouts.embedded')

@section('title', $experiment->name)

@php
    use App\Services\Experiments\ExperimentManager;
    use App\Services\Experiments\Results;
    $r = $results;
    $d = $r['decision'];
    $canManage = request()->attributes->get('storeUser')?->can('manage_experiences');
    $money = fn ($v) => $v === null ? '—' : money($v, $store->currency);
    $pct = fn ($v, $digits = 2) => $v === null ? '—' : number_format($v, $digits).'%';
    $primary = $experiment->primary_metric === 'conversion_rate' ? 'conversion_rate' : 'revenue_per_visitor';
    $tone = ['winner' => 'success', 'control' => 'info', 'no_winner' => 'info', 'guardrail' => 'warning', 'collecting' => 'info'][$d['state']];
    $lift = function ($c) use ($pct) {
        if (! $c || $c['lift'] === null) return '—';
        return sprintf('%+.1f%%', $c['lift']).' <span class="oo-muted oo-small">('.sprintf('%+.1f', $c['lift_low']).' to '.sprintf('%+.1f', $c['lift_high']).')</span>';
    };
    $colors = ['A' => '#8a8a8a', 'B' => '#6d5df6', 'C' => '#0c7a43'];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/experiments.css') }}?v={{ filemtime(public_path('css/experiments.css')) }}">
@endpush

@section('content')
<s-page heading="{{ $experiment->name }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.experiments.index', ['tab' => in_array($experiment->status, ['running', 'paused'], true) ? 'active' : 'completed']) }}">A/B tests</s-link>
    @if (request('error'))<s-banner tone="critical">{{ request('error') }}</s-banner>@endif

    <s-banner tone="{{ $tone }}" heading="{{ ['winner' => 'Winner declared', 'control' => 'The control wins', 'no_winner' => 'No clear winner', 'guardrail' => 'Guardrail breached', 'collecting' => 'Collecting data'][$d['state']] }}">
        <s-paragraph>{{ $d['headline'] }}</s-paragraph>
        @if ($d['state'] === 'collecting')<span class="oo-meter" style="display:block;max-width:320px"><i style="width:{{ round($d['progress'] * 100) }}%"></i></span>@endif
    </s-banner>

    <s-section heading="Summary">
        <dl class="oo-kv">
            <dt>Status</dt><dd>@include('app.experiments._status', ['status' => $experiment->status])</dd>
            <dt>Experience</dt><dd><s-link href="{{ app_route('app.cro.experiences.show', ['experience' => $experiment->experience_id]) }}">{{ $experiment->experience->name }}</s-link></dd>
            @if ($experiment->hypothesis)<dt>Hypothesis</dt><dd>{{ $experiment->hypothesis }}</dd>@endif
            <dt>Primary metric</dt><dd>{{ ExperimentManager::PRIMARY[$experiment->primary_metric] }}</dd>
            <dt>Duration</dt><dd>{{ $experiment->started_at ? floor($r['days']).' of at least '.$experiment->min_days.' days · since '.$experiment->started_at->toFormattedDateString() : 'Not started' }}{{ $experiment->ends_at ? ' · ends '.$experiment->ends_at->toFormattedDateString() : '' }}</dd>
            <dt>Minimum sample</dt><dd>{{ number_format($experiment->min_visitors) }} visitors and {{ number_format($experiment->min_conversions) }} conversions per variant</dd>
            <dt>Confidence</dt><dd>{{ round($r['confidence'] * 100, 1) }}% per comparison{{ count($r['variants']) > 2 ? ' (95% with the Bonferroni correction for '.(count($r['variants']) - 1).' comparisons)' : '' }}</dd>
            @if ($experiment->audience)<dt>Audience</dt><dd>{{ collect($experiment->audience)->map(fn ($v, $k) => ucfirst(str_replace('_', ' ', $k)).': '.(is_array($v) ? collect($v)->pluck('title')->implode(', ') : $v))->implode(' · ') }}</dd>@endif
        </dl>
        @if ($canManage)
            <div class="oo-inline" style="margin-top:12px">
                @if ($experiment->status === 'running')
                    <form method="POST" action="{{ app_route('app.experiments.action', ['experiment' => $experiment->id, 'action' => 'pause']) }}"><s-button type="submit">Pause</s-button></form>
                @elseif ($experiment->status === 'paused')
                    <form method="POST" action="{{ app_route('app.experiments.action', ['experiment' => $experiment->id, 'action' => 'resume']) }}"><s-button type="submit" variant="primary">Resume</s-button></form>
                    <s-button href="{{ app_route('app.experiments.edit', ['experiment' => $experiment->id]) }}">Edit setup</s-button>
                @endif
                @if (in_array($experiment->status, ['running', 'paused'], true))
                    <form method="POST" action="{{ app_route('app.experiments.action', ['experiment' => $experiment->id, 'action' => 'stop']) }}" data-confirm="Stop this test? Everyone will see the experience as published."><s-button type="submit" tone="critical">Stop test</s-button></form>
                @endif
                @if ($d['winner'] && $d['winner'] !== 'A' && in_array($experiment->status, ['running', 'paused', 'completed', 'stopped'], true))
                    <form method="POST" action="{{ app_route('app.experiments.action', ['experiment' => $experiment->id, 'action' => 'apply']) }}" data-confirm="Publish variant {{ $d['winner'] }} to the experience for everyone?">
                        <input type="hidden" name="variant" value="{{ $d['winner'] }}"><s-button type="submit" variant="primary">Apply winner ({{ $d['winner'] }})</s-button>
                    </form>
                @endif
                <s-button href="{{ app_route('app.experiments.export', ['experiment' => $experiment->id]) }}" target="_blank">Export CSV</s-button>
                <form method="POST" action="{{ app_route('app.experiments.duplicate', ['experiment' => $experiment->id]) }}"><s-button type="submit" variant="tertiary">Duplicate as new test</s-button></form>
            </div>
        @endif
    </s-section>

    <s-section heading="Variants">
        <div class="oo-scroll">
            <table class="oo-table stack xp-table">
                <thead><tr><th>Variant</th><th>Traffic</th><th>Visitors</th><th>Conversions</th><th>Conversion rate</th><th>Revenue</th><th>Revenue / visitor</th><th>AOV</th><th>Lift vs control ({{ $primary === 'conversion_rate' ? 'conversion' : 'revenue / visitor' }})</th><th>p-value</th></tr></thead>
                <tbody>
                    @foreach ($experiment->variants as $v)
                        @php($m = $r['variants'][$v->key])
                        @php($c = $r['comparisons'][$v->key][$primary] ?? null)
                        <tr class="{{ $d['winner'] === $v->key ? 'xp-win' : '' }}">
                            <td data-label="Variant"><span class="xp-dot" style="background:{{ $colors[$v->key] }}"></span><strong>{{ $v->key }}</strong> · {{ $v->name }}@if ($v->hidden) <s-badge>Holdout</s-badge>@endif @if ($d['winner'] === $v->key) <s-badge tone="success">Winner</s-badge>@endif</td>
                            <td data-label="Traffic">{{ $v->allocation }}%</td>
                            <td data-label="Visitors">{{ number_format($m['visitors']) }}</td>
                            <td data-label="Conversions">{{ number_format($m['conversions']) }}</td>
                            <td data-label="Conversion rate">{{ $pct($m['conversion_rate']) }}</td>
                            <td data-label="Revenue">{{ $money($m['revenue']) }}</td>
                            <td data-label="Revenue / visitor">{{ $money($m['revenue_per_visitor']) }}</td>
                            <td data-label="AOV">{{ $money($m['aov']) }}</td>
                            <td data-label="Lift">{!! $v->key === 'A' ? '<span class="oo-muted">Baseline</span>' : $lift($c) !!}</td>
                            <td data-label="p-value">{{ $v->key === 'A' ? '—' : Results::p($c['p'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="oo-muted oo-small">Lift is relative to the control, with its {{ round($r['confidence'] * 100, 1) }}% confidence interval in brackets. A visitor counts for the variant they first saw; their orders after that count for it.</p>
    </s-section>

    <s-section heading="Conversion rate over time (cumulative)">
        @php($series = $r['daily'])
        @php($top = max(0.1, collect($series)->flatten()->max()))
        @if (collect($series)->flatten()->sum() == 0)
            <s-paragraph><span class="oo-muted">The chart fills in as visitors convert.</span></s-paragraph>
        @else
            <svg class="xp-chart" viewBox="0 0 600 160" role="img" aria-label="Cumulative conversion rate by variant">
                @foreach ($series as $key => $points)
                    @php($vals = array_values($points))
                    @php($n = max(1, count($vals) - 1))
                    <polyline fill="none" stroke="{{ $colors[$key] }}" stroke-width="2.5" points="{{ collect($vals)->map(fn ($y, $i) => round($i / $n * 600, 1).','.round(155 - $y / $top * 145, 1))->implode(' ') }}" />
                @endforeach
            </svg>
            <p class="an-legend">@foreach ($series as $key => $p)<span><i style="background:{{ $colors[$key] }}"></i>{{ $key }}</span>@endforeach <span class="oo-muted">Top: {{ number_format($top, 2) }}%</span></p>
        @endif
    </s-section>

    @if ($experiment->secondary_metrics)
        <s-section heading="Secondary metrics">
            <table class="oo-table stack">
                <thead><tr><th>Metric</th>@foreach ($experiment->variants as $v)<th>{{ $v->key }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($experiment->secondary_metrics as $metric)
                        <tr>
                            <td data-label="Metric">{{ ExperimentManager::SECONDARY[$metric] ?? $metric }}</td>
                            @foreach ($experiment->variants as $v)
                                @php($value = $r['variants'][$v->key][$metric] ?? null)
                                <td data-label="{{ $v->key }}">{{ $metric === 'aov' ? $money($value) : ($metric === 'units_per_order' ? ($value === null ? '—' : number_format($value, 2)) : $pct($value, 1)) }}@if ($metric === 'aov' && $v->key !== 'A' && ($c = $r['comparisons'][$v->key]['aov'] ?? null)) <span class="oo-muted oo-small">p {{ Results::p($c['p']) }}</span>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </s-section>
    @endif

    @if ($experiment->guardrails)
        <s-section heading="Guardrails">
            <table class="oo-table stack">
                <thead><tr><th>Guardrail</th><th>Control</th>@foreach ($experiment->variants->where('key', '!=', 'A') as $v)<th>{{ $v->key }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($experiment->guardrails as $g)
                        <tr>
                            <td data-label="Guardrail">{{ ExperimentManager::GUARDRAILS[$g['metric']] ?? $g['metric'] }} <span class="oo-muted oo-small">(max +{{ $g['threshold'] }} pts)</span></td>
                            <td data-label="Control">{{ $pct($r['variants']['A'][$g['metric']] ?? null, 1) }}</td>
                            @foreach ($experiment->variants->where('key', '!=', 'A') as $v)
                                @php($gr = $r['comparisons'][$v->key]['guardrails'][$g['metric']] ?? null)
                                <td data-label="{{ $v->key }}">{{ $pct($r['variants'][$v->key][$g['metric']] ?? null, 1) }} @if ($gr && $gr['breached'])<s-badge tone="critical">Breached</s-badge>@elseif ($gr && $gr['change'] !== null)<s-badge tone="success">OK</s-badge>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </s-section>
    @endif

    <s-section heading="By device">
        @php($devices = collect($r['variants'])->flatMap(fn ($m) => array_keys($m['devices']))->unique()->sort()->values())
        @if ($devices->isEmpty())
            <s-paragraph><span class="oo-muted">No visitors yet.</span></s-paragraph>
        @else
            <table class="oo-table stack">
                <thead><tr><th>Device</th>@foreach ($experiment->variants as $v)<th>{{ $v->key }}: visitors · conversion</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr>
                            <td data-label="Device">{{ ucfirst($device) }}</td>
                            @foreach ($experiment->variants as $v)
                                @php($dv = $r['variants'][$v->key]['devices'][$device] ?? ['visitors' => 0, 'conversions' => 0])
                                <td data-label="{{ $v->key }}">{{ number_format($dv['visitors']) }} · {{ $dv['visitors'] ? number_format($dv['conversions'] / $dv['visitors'] * 100, 2).'%' : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </s-section>

    <s-section heading="Statistical method">
        <s-paragraph>Fixed-horizon frequentist test. Conversion rate uses a two-proportion z-test; revenue per visitor and AOV use Welch's t-test. Significance is {{ round((1 - $r['confidence']) * 100, 2) }}% per comparison ({{ count($r['variants']) > 2 ? 'Bonferroni-corrected for '.(count($r['variants']) - 1).' comparisons' : 'one comparison' }}). A winner needs at least {{ $experiment->min_days }} days, {{ number_format($experiment->min_visitors) }} visitors and {{ number_format($experiment->min_conversions) }} conversions in every variant, a significant improvement on the primary metric, and no breached guardrail. Looking at results early doesn't change them, but don't stop a test just because it looks good.</s-paragraph>
    </s-section>

    <s-section heading="History">
        <ul class="xp-history">
            @foreach ($logs as $log)<li><span class="oo-muted">{{ $log->created_at->toDayDateTimeString() }}</span> {{ $log->message }}@if ($log->user) <span class="oo-muted">· {{ $log->user->name ?? $log->user->email }}</span>@endif</li>@endforeach
        </ul>
    </s-section>
</s-page>
@endsection
