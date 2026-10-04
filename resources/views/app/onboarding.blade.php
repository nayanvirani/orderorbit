@extends('layouts.embedded')

@section('title', 'Get started')

@section('content')
<s-page heading="Welcome to OrderOrbit Space">
    <x-app.hero eyebrow="Get started" title="Welcome to <em>OrderOrbit Space.</em>"
        lead="Eight short steps from connecting your store to seeing your first results. You can leave at any time and pick up where you left off." />
    <div class="oo-steps" aria-label="Onboarding progress">
        @foreach ($steps as $i => $label)
            @php($n = $i + 1)
            <a href="{{ app_route('app.onboarding', ['step' => $n]) }}" style="text-decoration:none"><span class="{{ $n === $step ? 'current' : ($done[$n] ? 'done' : '') }}">{{ $done[$n] ? '✓' : $n }}. {{ $label }}</span></a>
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
    @elseif ($step === 2)
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
    @elseif ($step === 3)
        <s-section heading="3. Choose your first experience">
            <p class="oo-muted">Recommended for <strong>{{ $goals[$store->goal]['label'] ?? 'your goal' }}</strong>. You can add more later.</p>
            <div class="ob-types">
                @foreach ($recommended as [$label, $help, $route, $params])
                    <a class="ob-type" href="{{ app_route($route, $params + ['onboarding' => 1]) }}"><h4>{{ $label }}</h4><p>{{ $help }}</p><div class="ob-row">Start →</div></a>
                @endforeach
            </div>
            <p class="oo-small"><a href="{{ app_route('app.cro.experiences.create') }}">See every experience type</a> · <a href="{{ app_route('app.onboarding', ['step' => 2]) }}">Change goal</a></p>
        </s-section>
    @elseif ($step <= 7)
        @php($titles = [4 => 'Choose a template', 5 => 'Configure it', 6 => 'Preview on desktop and mobile', 7 => 'Publish and place it'])
        <s-section heading="{{ $step }}. {{ $titles[$step] }}">
            @if (! $first)
                <s-paragraph>Start by choosing your first experience.</s-paragraph>
                <s-button variant="primary" href="{{ app_route('app.onboarding', ['step' => 3]) }}">Choose an experience</s-button>
            @else
                <s-paragraph>{{ [4 => 'Pick the layout that fits your store. You can switch templates any time without losing your content.', 5 => 'Add your products, text and offer, and match your brand in the Design step.', 6 => 'Use the preview on the right of the builder, and switch between desktop and mobile.', 7 => 'Publish, then place the block in the Theme Editor (or the checkout editor for checkout blocks). The builder shows exactly where.'][$step] }}</s-paragraph>
                <dl class="oo-kv">
                    <dt>Your first experience</dt><dd>{{ $first->name }} · {{ \App\Experiences\Registry::has($first->type) ? \App\Experiences\Registry::type($first->type)['label'] : $first->type }}</dd>
                    <dt>Status</dt><dd>{{ ucfirst($first->status) }}{{ $first->status === 'published' ? ' · placement: '.str_replace('_', ' ', $first->placement_status ?? 'not checked') : '' }}</dd>
                </dl>
                <div class="oo-inline" style="margin-top:12px">
                    <s-button variant="primary" href="{{ in_array($first->type, ['bundles'], true) ? app_route('app.bundles.edit', ['bundle' => $first->id]) : ($first->type === 'progressive-gifts' ? app_route('app.gifts.edit', ['gift' => $first->id]) : app_route('app.cro.experiences.edit', ['experience' => $first->id])) }}">Open the builder</s-button>
                    @if ($step === 7 && $first->status === 'published')<s-button href="{{ $store->themeEditorUrl(\App\Experiences\Registry::has($first->type) ? \App\Experiences\Registry::type($first->type)['surface'] : 'product') }}" target="_top">{{ $store->editorLabel(\App\Experiences\Registry::has($first->type) ? \App\Experiences\Registry::type($first->type)['surface'] : 'product') }}</s-button>@endif
                    <s-button variant="tertiary" href="{{ app_route('app.onboarding', ['step' => $step + 1]) }}">Next step</s-button>
                </div>
            @endif
        </s-section>
    @else
        <s-section heading="8. Verify analytics">
            <dl class="oo-kv">
                <dt>Analytics pixel</dt><dd>@if ($pixelConnected)<s-badge tone="success">Connected</s-badge>@else<s-badge tone="warning">Not connected</s-badge> <s-link href="{{ app_route('app.analytics') }}">Connect</s-link>@endif</dd>
                <dt>First event</dt><dd>@if ($lastEvent)<s-badge tone="success">Received</s-badge> {{ \Illuminate\Support\Carbon::parse($lastEvent)->diffForHumans() }}@else<s-badge>Waiting</s-badge> Open your store and view a product (with analytics cookies allowed), then check again.@endif</dd>
            </dl>
            <div class="oo-inline" style="margin-top:12px">
                <s-button href="https://{{ $store->shop_domain }}" target="_blank">Open your store</s-button>
                <s-button href="{{ app_route('app.onboarding', ['step' => 8]) }}">Check again</s-button>
                @if ($lastEvent)<s-button variant="primary" href="{{ app_route('app.dashboard') }}">Go to your dashboard</s-button>@endif
            </div>
        </s-section>
    @endif
</s-page>
@endsection
