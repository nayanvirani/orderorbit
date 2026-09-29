@extends('layouts.embedded')

@section('title', 'CRO')

@section('content')
<s-page heading="CRO overview">
    <x-app.hero eyebrow="Convert" title="Every conversion tool, <em>one design.</em>"
        lead="Bundles, free gifts, shipping progress, quantity breaks, upsells, countdowns, sticky add-to-cart and trust — sharing your brand and one set of numbers.">
        <s-button variant="primary" href="{{ app_route('app.cro.experiences.create') }}">Create experience</s-button>
        <s-button href="{{ app_route('app.cro.experiences.index') }}">All experiences</s-button>
    </x-app.hero>

    @if ($notPlaced)
        <s-banner tone="warning">
            <s-paragraph>{{ $notPlaced }} published {{ \Illuminate\Support\Str::plural('experience', $notPlaced) }} {{ $notPlaced === 1 ? 'isn\'t' : 'aren\'t' }} placed in your theme yet. Add the OrderOrbit experience block in the Theme Editor.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route('app.cro.experiences.index', ['status' => 'not_placed']) }}">View experiences</s-button>
        </s-banner>
    @endif

    <s-section>
        <div class="ob-kpis">
            <div class="ob-kpi"><small>Active experiences</small><b>{{ $activeUsed }}<span style="display:inline;font:400 18px var(--ob-serif);color:var(--ob-muted)"> / {{ $activeLimit === null ? '∞' : $activeLimit }}</span></b><span>On your current plan</span></div>
            <div class="ob-kpi"><small>Drafts</small><b>{{ $counts['draft'] ?? 0 }}</b><span>Not live yet</span></div>
            <div class="ob-kpi"><small>Paused</small><b>{{ $counts['paused'] ?? 0 }}</b><span>Hidden from shoppers</span></div>
            <div class="ob-kpi"><small>Views · revenue</small><b>—</b><span>Arrives with analytics</span></div>
        </div>
    </s-section>

    <s-section heading="Create an experience">
        <div class="ob-types">
            @foreach (\App\Experiences\Registry::types() as $key => $type)
                <div class="ob-type">
                    <h4>{{ $type['label'] }}
                        @if ($byType[$key] ?? 0)<span class="ob-badge soft">{{ $byType[$key] }}</span>@endif
                        @unless ($type['publishable'])<span class="ob-badge soft">Preview</span>@endunless
                    </h4>
                    <p>{{ $type['description'] }}</p>
                    <div class="ob-row">
                        <a href="{{ app_route('app.cro.experiences.create', ['type' => $key]) }}">Create →</a>
                        <a href="{{ app_route('app.cro.type', ['type' => $key]) }}" style="color:var(--ob-muted)">View all</a>
                    </div>
                </div>
            @endforeach
        </div>
    </s-section>

    <s-section heading="Recently updated">
        @forelse ($recent as $experience)
            <s-stack direction="inline" gap="small-200" alignItems="center" style="padding:6px 0;border-bottom:1px solid #f1f1f1">
                <s-link href="{{ app_route('app.cro.experiences.show', ['experience' => $experience->id]) }}">{{ $experience->name }}</s-link>
                <span class="oo-muted">{{ \App\Experiences\Registry::type($experience->type)['label'] }}</span>
                @include('app.cro._status')
                <span class="oo-muted oo-small">{{ $experience->updated_at->diffForHumans() }}</span>
            </s-stack>
        @empty
            <x-app.empty title="Create your first experience" text="Pick a template, customise it and publish it from the Theme Editor.">
                <s-button variant="primary" href="{{ app_route('app.cro.experiences.create') }}">Create experience</s-button>
            </x-app.empty>
        @endforelse
    </s-section>
</s-page>
@endsection
