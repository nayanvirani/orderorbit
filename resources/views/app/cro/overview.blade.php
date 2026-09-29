@extends('layouts.embedded')

@section('title', 'CRO')

@section('content')
<s-page heading="CRO overview">
    <s-button slot="primary-action" variant="primary" href="{{ app_route('app.cro.experiences.create') }}">Create experience</s-button>

    @if ($notPlaced)
        <s-banner tone="warning">
            <s-paragraph>{{ $notPlaced }} published {{ \Illuminate\Support\Str::plural('experience', $notPlaced) }} {{ $notPlaced === 1 ? 'isn\'t' : 'aren\'t' }} placed in your theme yet. Add the OrderOrbit experience block in the Theme Editor.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route('app.cro.experiences.index', ['status' => 'not_placed']) }}">View experiences</s-button>
        </s-banner>
    @endif

    <s-section>
        <s-grid gridTemplateColumns="repeat(auto-fit, minmax(150px, 1fr))" gap="base">
            <s-box padding="base" border="base" borderRadius="base"><s-text color="subdued">Active experiences</s-text><s-heading>{{ $activeUsed }}<span class="oo-muted" style="font-weight:400"> / {{ $activeLimit === null ? '∞' : $activeLimit }}</span></s-heading></s-box>
            <s-box padding="base" border="base" borderRadius="base"><s-text color="subdued">Drafts</s-text><s-heading>{{ $counts['draft'] ?? 0 }}</s-heading></s-box>
            <s-box padding="base" border="base" borderRadius="base"><s-text color="subdued">Paused</s-text><s-heading>{{ $counts['paused'] ?? 0 }}</s-heading></s-box>
            <s-box padding="base" border="base" borderRadius="base"><s-text color="subdued">Views · Revenue</s-text><s-heading>—</s-heading><s-text color="subdued">Arrives with analytics</s-text></s-box>
        </s-grid>
    </s-section>

    <s-section heading="Create an experience">
        <s-grid gridTemplateColumns="repeat(auto-fit, minmax(220px, 1fr))" gap="base">
            @foreach (\App\Experiences\Registry::types() as $key => $type)
                <s-box padding="base" border="base" borderRadius="base">
                    <s-stack gap="small-200">
                        <s-stack direction="inline" gap="small-200" alignItems="center">
                            <strong>{{ $type['label'] }}</strong>
                            @if ($byType[$key] ?? 0)<s-badge>{{ $byType[$key] }}</s-badge>@endif
                            @unless ($type['publishable'])<s-badge tone="info">Preview</s-badge>@endunless
                        </s-stack>
                        <s-text color="subdued">{{ $type['description'] }}</s-text>
                        <s-stack direction="inline" gap="small-200">
                            <s-button href="{{ app_route('app.cro.experiences.create', ['type' => $key]) }}">Create</s-button>
                            <s-button variant="tertiary" href="{{ app_route('app.cro.type', ['type' => $key]) }}">View all</s-button>
                        </s-stack>
                    </s-stack>
                </s-box>
            @endforeach
        </s-grid>
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
            <s-paragraph>Create your first experience. Pick a template, customise it and publish it from the Theme Editor.</s-paragraph>
        @endforelse
    </s-section>
</s-page>
@endsection
