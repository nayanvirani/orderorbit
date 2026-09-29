@extends('layouts.embedded')

@section('title', 'Get started')

@section('content')
<s-page heading="Welcome to OrderOrbit">
    <x-app.hero eyebrow="Get started" title="Welcome to <em>OrderOrbit.</em>"
        lead="Two quick steps now: confirm your store connection and choose what to improve first. We'll recommend where to start." />
    <div class="oo-steps" aria-label="Onboarding progress">
        @foreach ($steps as $i => $label)
            @php($n = $i + 1)
            <span class="{{ $n < $step || ($n === 2 && $store->goal && $step !== 2) || ($n === 1 && $step > 1) ? 'done' : ($n === $step ? 'current' : '') }}">{{ $n }}. {{ $label }}</span>
        @endforeach
    </div>

    @if ($step === 1)
        <s-section heading="Your store is connected">
            <dl class="oo-kv">
                <dt>Store</dt><dd>{{ $store->name ?? $store->shop_domain }}</dd>
                <dt>Currency</dt><dd>{{ $store->currency ?? '—' }}</dd>
                <dt>Selling currencies</dt><dd>{{ implode(', ', $store->capability('presentment_currencies') ?? []) ?: '—' }}</dd>
                <dt>Permissions</dt>
                <dd>
                    @if ($missingScopes)
                        <s-badge tone="critical">Missing: {{ implode(', ', $missingScopes) }}</s-badge>
                    @else
                        <s-badge tone="success">All granted</s-badge>
                    @endif
                </dd>
            </dl>
            <s-stack direction="inline" gap="small-200" style="margin-top:16px">
                <s-button variant="primary" href="{{ app_route('app.onboarding', ['step' => 2]) }}">Next</s-button>
            </s-stack>
        </s-section>
    @else
        <s-section heading="What do you want to improve first?">
            <s-paragraph>We'll recommend experiences and templates for your goal. You can change this any time.</s-paragraph>
            <form method="POST" action="{{ app_route('app.onboarding.update') }}">
                @foreach ($goals as $key => $goal)
                    <label class="oo-radio">
                        <input type="radio" name="goal" value="{{ $key }}" @checked($store->goal === $key) required>
                        <span><strong>{{ $goal['label'] }}</strong><small>{{ $goal['help'] }}</small></span>
                    </label>
                @endforeach
                <s-stack direction="inline" gap="small-200">
                    <s-button href="{{ app_route('app.onboarding', ['step' => 1]) }}">Back</s-button>
                    <s-button type="submit" variant="primary">Next</s-button>
                </s-stack>
            </form>
        </s-section>
        <s-paragraph><span class="oo-muted">Steps 3–8 — choosing, configuring and publishing your first experience — open with the experience builder.</span></s-paragraph>
    @endif
</s-page>
@endsection
