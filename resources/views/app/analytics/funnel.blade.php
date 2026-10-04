@extends('layouts.embedded')

@section('title', $funnel->name)

@php
    use App\Services\Analytics\Funnels;
    $canEdit = request()->attributes->get('storeUser')?->can('manage_experiences');
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1).'%';
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/bundles.css') }}?v={{ filemtime(public_path('css/bundles.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v={{ filemtime(public_path('css/analytics.css')) }}">
@endpush

@section('content')
<s-page heading="{{ $funnel->name }}">
    @include('app.analytics._nav', ['lockedTitle' => 'Funnels are on Growth and Scale'])

    @if ($report)
        @php($steps = $report['steps'])
        @php($overall = $steps[0]['visitors'] ? end($steps)['visitors'] / $steps[0]['visitors'] * 100 : null)
        <s-section heading="Conversion · {{ Funnels::WINDOWS[$funnel->within] ?? '' }}">
            <div class="ob-kpis">
                <x-app.kpi label="Entered" icon="users" tone="analytics" :value="number_format($steps[0]['visitors'])" sub="Visitors who did the first step" />
                <x-app.kpi label="Completed" icon="bag" tone="upsells" :value="number_format(end($steps)['visitors'])" sub="Visitors who did every step" />
                <x-app.kpi label="Funnel conversion" icon="target" tone="countdown" :value="$pct($overall)" :trend="$previous && $previous['steps'][0]['visitors'] ? \App\Services\Analytics\Analytics::trend($overall, end($previous['steps'])['visitors'] / $previous['steps'][0]['visitors'] * 100) : null" :spark="collect($report['daily'])->map(fn ($d) => $d['entered'] ? $d['completed'] / $d['entered'] * 100 : 0)->values()->all()" sub="First step to last" />
            </div>

            <nav class="bx-tabs" aria-label="Compare" style="margin-top:16px">
                <a href="{{ request()->fullUrlWithQuery(['compare' => null]) }}" class="{{ ! $compare ? 'on' : '' }}">No comparison</a>
                @foreach (Funnels::COMPARE + ['period' => 'Previous period'] as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['compare' => $key]) }}" class="{{ $compare === $key ? 'on' : '' }}">By {{ strtolower($label) }}</a>
                @endforeach
            </nav>

            <ol class="an-funnel">
                @foreach ($steps as $i => $step)
                    <li>
                        <div class="an-funnel-head">
                            <strong>{{ $i + 1 }}. {{ $step['label'] }}</strong>
                            @if (! empty($step['experience']))<span class="oo-muted"> · {{ $experiences[$step['experience']]->name ?? $step['experience'] }}</span>@endif
                        </div>
                        <div class="an-funnel-bar"><i style="width:{{ $step['from_first'] }}%"></i><span>{{ number_format($step['visitors']) }} visitors · {{ $pct($step['from_first']) }}</span></div>
                        <div class="an-funnel-meta oo-small">
                            @if ($i > 0)
                                <span>{{ $pct($step['from_previous']) }} from the previous step</span>
                                <span class="an-drop">{{ number_format($step['dropped']) }} dropped off</span>
                                <span>Median time from the previous step: {{ Funnels::human($step['median_seconds']) }}</span>
                            @endif
                            @if ($previous)
                                <span>Previous period: {{ number_format($previous['steps'][$i]['visitors']) }} ({{ $pct($previous['steps'][$i]['from_first']) }})</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
            @if ($report['truncated'])
                <p class="oo-muted oo-small">This period has a lot of events; the report uses the first {{ number_format(Funnels::MAX_EVENTS) }}. Pick a shorter range for exact numbers.</p>
            @endif
        </s-section>

        @if ($report['segments'])
            <s-section heading="By {{ strtolower(Funnels::COMPARE[$compare]) }}">
                <div class="oo-scroll">
                    <table class="oo-table stack">
                        <thead><tr><th>{{ Funnels::COMPARE[$compare] }}</th>@foreach ($steps as $i => $step)<th>{{ $i + 1 }}. {{ $step['label'] }}</th>@endforeach<th>Conversion</th></tr></thead>
                        <tbody>
                            @foreach ($report['segments'] as $segment => $counts)
                                <tr>
                                    <td data-label="{{ Funnels::COMPARE[$compare] }}">{{ ucfirst($segment) }}</td>
                                    @foreach ($counts as $i => $n)<td data-label="{{ $steps[$i]['label'] }}">{{ number_format($n) }}</td>@endforeach
                                    <td data-label="Conversion">{{ $pct($counts[0] ? end($counts) / $counts[0] * 100 : null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </s-section>
        @elseif ($compare && $compare !== 'period')
            <s-section><s-paragraph><span class="oo-muted">Nothing to compare yet in this period.</span></s-paragraph></s-section>
        @endif
    @endif

    @if ($canEdit && ! $locked)
        <s-section heading="Edit funnel">
            <details><summary>Change the steps, name or window</summary>
                @include('app.analytics._funnel_form', ['action' => app_route('app.analytics.funnels.update', ['funnel' => $funnel->id])])
            </details>
            <form method="POST" action="{{ app_route('app.analytics.funnels.destroy', ['funnel' => $funnel->id]) }}" data-confirm="Delete this funnel? Your events are kept." style="margin-top:12px">
                <s-button type="submit" tone="critical" variant="tertiary">Delete funnel</s-button>
            </form>
        </s-section>
    @endif
</s-page>
@endsection
