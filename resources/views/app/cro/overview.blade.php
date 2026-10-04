@extends('layouts.embedded')

@section('title', 'CRO')

@section('content')
<s-page heading="CRO overview">
    <x-app.hero eyebrow="CRO" icon="sparkle" title="Every conversion tool, <em>one design.</em>"
        lead="Bundles, progressive gifts, cart upsells, countdowns, sticky add to cart and trust badges — sharing your brand, with savings applied at checkout and one set of numbers.">
        <s-button variant="primary" href="{{ app_route('app.bundles.types') }}">Create a bundle</s-button>
        <s-button href="{{ app_route('app.analytics') }}">View analytics</s-button>
    </x-app.hero>

    @if ($notPlaced)
        <s-banner tone="warning">
            <s-paragraph>{{ $notPlaced }} published {{ \Illuminate\Support\Str::plural('experience', $notPlaced) }} {{ $notPlaced === 1 ? 'isn\'t' : 'aren\'t' }} placed in your theme yet. Add the OrderOrbit Space block in the Theme Editor.</s-paragraph>
            <s-button slot="secondary-actions" href="{{ app_route('app.cro.experiences.index', ['status' => 'not_placed']) }}">View experiences</s-button>
        </s-banner>
    @endif

    <s-section>
        <div class="ob-kpis">
            <div class="ob-kpi"><small>Active experiences</small><b>{{ $activeUsed }}<span style="display:inline;font:400 18px var(--ob-serif);color:var(--ob-muted)"> / {{ $activeLimit === null ? '∞' : $activeLimit }}</span></b><span>On your current plan</span></div>
            <div class="ob-kpi"><small>Drafts</small><b>{{ $counts['draft'] ?? 0 }}</b><span>Not live yet</span></div>
            <div class="ob-kpi"><small>Paused</small><b>{{ $counts['paused'] ?? 0 }}</b><span>Hidden from shoppers</span></div>
            <div class="ob-kpi"><small>Revenue from offers · 30 days</small><b style="font-size:26px">{{ money($summary['influenced_revenue'], $summary['currency']) }}</b><span>{{ number_format($summary['influenced_orders']) }} orders with an offer</span></div>
        </div>
    </s-section>

    <s-section heading="Features">
        <div class="ob-features">
            @foreach ($features as $f)
                @php($href = isset($f['module']) ? app_route($f['module']) : app_route('app.features.show', ['feature' => $f['key']]))
                <a class="ob-feature t-{{ $f['tone'] ?? 'default' }}" href="{{ $href }}">
                    <span class="ob-feature-shot"><span class="oo-preview" data-render="{{ json_encode($f['preview']) }}"></span></span>
                    <span class="ob-feature-body">
                        <strong><x-app.icon :name="$f['icon'] ?? 'sparkle'" size="sm" />{{ $f['label'] }} @if ($n = collect($f['types'])->sum(fn ($t) => $byType[$t] ?? 0))<span class="ob-badge soft">{{ $n }}</span>@endif</strong>
                        <span>{{ strip_tags($f['lead']) }}</span>
                        <em>Open →</em>
                    </span>
                </a>
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

@push('head')
    <link rel="stylesheet" href="{{ route('storefront.asset', 'orderorbit.css') }}">
@endpush

@push('scripts')
    <script src="{{ route('storefront.asset', 'orderorbit.js') }}"></script>
    <script src="{{ asset('js/checkout-preview.js') }}?v={{ filemtime(public_path('js/checkout-preview.js')) }}"></script>
    <link rel="stylesheet" href="{{ asset('css/checkout-preview.css') }}?v={{ filemtime(public_path('css/checkout-preview.css')) }}">
    <script>
        document.querySelectorAll('[data-render]').forEach((el) => {
            window.OrderOrbit.render(el, JSON.parse(el.dataset.render), { preview: true, currency: @json($store->currency ?? 'USD'), cartTotal: 6000, productPrice: 2900, productTitle: 'Glow Serum', page: 'product' });
        });
    </script>
@endpush
