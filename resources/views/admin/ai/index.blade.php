@extends('admin.layout')
@section('title', 'AI providers')
@section('content')
@php($states = ['ok' => ['ok', 'Ready'], 'resting' => ['warn', 'Resting'], 'failing' => ['bad', 'Failing'], 'off' => ['', 'Off']])
<div class="ad-head">
    <div><h1>AI providers</h1><p>The services the Morning Brief and offer ideas use. Add several, free tiers included: they're tried top to bottom, and when one fails or hits its rate limit the next answers.</p></div>
    <div class="ad-actions">
        <details class="ad-menu">
            <summary class="ad-btn primary">Add provider</summary>
            <div class="ad-pop" style="min-width: 280px">
                @foreach ($presets as $key => $p)<a class="ad-btn" style="justify-content: flex-start" href="{{ route('admin.ai.create', ['preset' => $key]) }}">{{ $p[0] }}</a>@endforeach
            </div>
        </details>
    </div>
</div>

@unless ($settings['enabled'])<div class="ad-note warn"><b>AI is switched off.</b> The Morning Brief and offer ideas pause until you switch it on below.</div>@endunless
@if ($providers->isEmpty())
    <div class="ad-note">{!! $envKey ? 'Using the <b>ANTHROPIC_API_KEY</b> Railway variable. Add providers here to use others (free tiers included) or to add failover.' : 'No provider yet. Google Gemini, Groq, OpenRouter and Mistral all have free tiers: add one with its API key.' !!}</div>
@endif
<div class="ad-note">Only totals and product names are sent to a provider, never customer names, emails or addresses. Some free tiers (e.g. Gemini's) may use prompts to improve the provider's own products; use a paid tier where that matters.</div>

<div class="ad-kpis">
    <div><small>Requests this month</small><b>{{ number_format($stats['requests']) }}</b><span>From {{ $stats['stores'] }} {{ \Illuminate\Support\Str::plural('store', $stats['stores']) }}</span></div>
    <div class="{{ $stats['failed'] ? 'alert' : '' }}"><small>Failed this month</small><b>{{ number_format($stats['failed']) }}</b><span>Each one tried the next provider</span></div>
    <div><small>Cost this month</small><b>${{ number_format($stats['cost'], 2) }}</b><span>From the prices set per provider</span></div>
</div>

<section class="ad-card flush">
    <header><h2>Providers</h2><span class="ad-muted">Tried top to bottom</span></header>
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>Order</th><th>Provider</th><th>Status</th><th>This month</th><th>Last used</th><th></th></tr></thead>
            <tbody>
                @forelse ($providers as $i => $p)
                    @php([$tone, $label] = $states[$p->state()])
                    <tr>
                        <td class="ad-order">
                            <form method="POST" action="{{ route('admin.ai.action', [$p->id, 'up']) }}">@csrf<button class="ad-icon-btn" @disabled($i === 0) aria-label="Move up">↑</button></form>
                            <form method="POST" action="{{ route('admin.ai.action', [$p->id, 'down']) }}">@csrf<button class="ad-icon-btn" @disabled($loop->last) aria-label="Move down">↓</button></form>
                        </td>
                        <td><a class="ad-strong" href="{{ route('admin.ai.edit', $p->id) }}">{{ $p->name }}</a><div class="ad-muted">{{ $presets[$p->preset][0] ?? $p->preset }} · {{ $p->model }} · {{ $p->price_in || $p->price_out ? '$'.$p->price_in.' / $'.$p->price_out.' per M tokens' : 'free' }}</div></td>
                        <td>
                            <span class="ad-badge {{ $tone }}">{{ $label }}</span>
                            @if ($p->paused_until && $p->paused_until->isFuture())<div class="ad-muted ad-small">Until {{ $p->paused_until->diffForHumans() }}</div>@endif
                            @if ($p->last_error && $p->state() !== 'ok')<div class="ad-muted ad-small ad-clip" title="{{ $p->last_error }}">{{ \Illuminate\Support\Str::limit($p->last_error, 90) }}</div>@endif
                        </td>
                        <td>{{ $p->month === now()->format('Y-m') ? number_format($p->requests_month) : 0 }} requests</td>
                        <td>{{ $p->last_used_at?->diffForHumans() ?? '—' }}</td>
                        <td class="ad-row-actions">
                            <form method="POST" action="{{ route('admin.ai.test', $p->id) }}">@csrf<button class="ad-btn">Test</button></form>
                            @if ($p->state() === 'resting')<form method="POST" action="{{ route('admin.ai.action', [$p->id, 'resume']) }}">@csrf<button class="ad-btn">Resume</button></form>@endif
                            <form method="POST" action="{{ route('admin.ai.action', [$p->id, 'toggle']) }}">@csrf<button class="ad-btn">{{ $p->is_active ? 'Switch off' : 'Switch on' }}</button></form>
                            <form method="POST" action="{{ route('admin.ai.action', [$p->id, 'delete']) }}" onsubmit="return confirm('Remove {{ $p->name }}? Its key is deleted.')">@csrf<button class="ad-btn">Remove</button></form>
                        </td>
                    </tr>
                @empty <tr><td colspan="6" class="ad-empty">No providers yet.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="ad-card">
    <h2>Settings</h2>
    <form method="POST" action="{{ route('admin.ai.settings') }}" class="ad-form" style="max-width: 520px">
        @csrf
        <label style="display: flex; gap: 8px; align-items: center"><input type="checkbox" name="enabled" value="1" @checked($settings['enabled']) style="width: auto"> AI features on</label>
        <label>Monthly AI budget per Premium store (USD)<input type="number" step="0.01" min="0" name="budget_premium" value="{{ $settings['budget_premium'] }}"><small class="ad-muted">Counted from each provider's prices; free providers cost $0.</small></label>
        <label>Requests per store per month<input type="number" min="0" name="requests_per_store" value="{{ $settings['requests_per_store'] }}"><small class="ad-muted">Keeps one store from using up a free tier's limits. When reached, AI pauses for that store until the 1st.</small></label>
        <div><button class="ad-btn primary" type="submit">Save settings</button></div>
    </form>
</section>

<section class="ad-card flush">
    <header><h2>Recent requests</h2></header>
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>When</th><th>Store</th><th>Feature</th><th>Provider · model</th><th>Tokens</th><th>Cost</th><th>Result</th></tr></thead>
            <tbody>
                @forelse ($recent as $r)
                    <tr><td>{{ \Illuminate\Support\Carbon::parse($r->created_at)->diffForHumans() }}</td><td>#{{ $r->store_id }}</td><td>{{ str_replace('_', ' ', $r->feature) }}</td><td>{{ $r->provider ?? '—' }}<div class="ad-muted ad-small">{{ $r->model }}</div></td>
                        <td>{{ number_format($r->input_tokens + $r->output_tokens) }}</td><td>${{ number_format($r->cost, 4) }}</td>
                        <td>{!! $r->outcome === 'ok' ? '<span class="ad-badge ok">OK</span>' : '<span class="ad-badge bad">'.e(ucfirst($r->outcome)).'</span>' !!}</td></tr>
                @empty <tr><td colspan="7" class="ad-empty">No AI requests yet.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
