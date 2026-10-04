@extends('layouts.embedded')

@section('title', 'A/B tests')

@php
    use App\Services\Experiments\ExperimentManager;
    $canManage = request()->attributes->get('storeUser')?->can('manage_experiences');
    $resultLabel = fn ($s) => match ($s['decision']['state'] ?? null) {
        'winner' => 'Winner: '.$s['decision']['winner'], 'control' => 'Control wins', 'no_winner' => 'No clear winner', 'guardrail' => 'Guardrail breached', default => 'Running',
    };
@endphp

@section('content')
<s-page inlineSize="large" heading="A/B tests">
    <x-app.hero eyebrow="A/B testing" icon="target" tone="analytics" title="Test before you <em>commit.</em>"
        lead="Split visitors between versions of a live experience and see which earns more. A winner is only named once the test has enough days, visitors and conversions to be sure.">
        <s-button href="{{ route('site.docs', 'ab-testing') }}" target="_blank">View documentation</s-button>
    </x-app.hero>

    @if (request('error'))<s-banner tone="critical">{{ request('error') }}</s-banner>@endif
    @unless ($store->planIncludes('ab_testing'))
        <s-banner tone="info" heading="A/B tests run on Growth and Scale">
            <s-paragraph>You can set up tests now and launch them after upgrading.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route('app.settings.billing') }}">See plans</s-button>
        </s-banner>
    @endunless

    @if ($canManage)
        <s-section heading="Create a test">
            @if ($testable->isEmpty())
                <s-paragraph>Publish an experience first, such as an upsell, countdown, sticky add to cart, trust or checkout block. Tests split the traffic of a live experience.</s-paragraph>
            @else
                <form method="POST" action="{{ app_route('app.experiments.store') }}" class="oo-form-row">
                    <label class="oo-field" style="flex:1;min-width:240px">Experience to test
                        <select name="experience_id" required>
                            @foreach ($testable as $e)
                                <option value="{{ $e->id }}">{{ $e->name }} · {{ \App\Experiences\Registry::type($e->type)['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <s-button type="submit" variant="primary">Create test</s-button>
                </form>
                <p class="oo-muted oo-small">Storefront experiences and checkout, Thank You and Order Status blocks can be tested. Bundles, Progressive gifts, the post-purchase offer and customer account blocks can't yet.</p>
            @endif
        </s-section>
    @endif


    <s-section>
        <form method="GET" action="{{ app_route('app.experiments.index') }}" class="oo-form-row" style="margin-bottom:12px">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="oo-field" style="flex:1">Search<input type="search" name="q" value="{{ request('q') }}" placeholder="Test name"></label>
        </form>
        @if ($experiments->isEmpty())
            <s-paragraph><span class="oo-muted">{{ ['active' => 'No tests running.', 'drafts' => 'No drafts.', 'completed' => 'No finished tests yet.'][$tab] }}</span></s-paragraph>
        @else
            <div class="oo-scroll">
                <table class="oo-table stack">
                    <thead><tr><th>Test</th><th>Experience</th><th>Variants</th><th>Primary metric</th><th>Visitors per variant</th><th>Progress</th><th>Days</th><th>Status</th><th>Result</th></tr></thead>
                    <tbody>
                        @foreach ($experiments as $x)
                            @php($s = $summaries[$x->id])
                            <tr>
                                <td data-label="Test"><s-link href="{{ app_route($x->status === 'draft' ? 'app.experiments.edit' : 'app.experiments.show', ['experiment' => $x->id]) }}">{{ $x->name }}</s-link></td>
                                <td data-label="Experience">{{ $x->experience->name }}</td>
                                <td data-label="Variants">{{ $x->variants->map(fn ($v) => $v->key.' '.$v->allocation.'%')->implode(' · ') }}</td>
                                <td data-label="Primary metric">{{ ExperimentManager::PRIMARY[$x->primary_metric] ?? $x->primary_metric }}</td>
                                <td data-label="Visitors per variant">{{ $s ? collect($s['variants'])->map(fn ($m) => number_format($m['visitors']))->implode(' / ') : '—' }}</td>
                                <td data-label="Progress">@if ($s)<span class="oo-meter" style="display:block;width:90px"><i style="width:{{ round($s['decision']['progress'] * 100) }}%"></i></span>@else — @endif</td>
                                <td data-label="Days">{{ $x->started_at ? floor($x->daysRunning()) : '—' }}</td>
                                <td data-label="Status">@include('app.experiments._status', ['status' => $x->status])</td>
                                <td data-label="Result">{{ $s ? $resultLabel($s) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </s-section>
</s-page>
@endsection
