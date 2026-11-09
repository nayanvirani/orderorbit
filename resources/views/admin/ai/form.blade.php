@extends('admin.layout')
@section('title', $provider ? 'Edit AI provider' : 'Add AI provider')
@section('content')
@php([$label, $driver, $baseUrl, $model, $prices, $note] = $presets[$preset])
<p class="ad-crumbs"><a href="{{ route('admin.ai') }}">AI providers</a> / {{ $provider ? $provider->name : 'Add '.$label }}</p>
<div class="ad-head"><div><h1>{{ $provider ? $provider->name : $label }}</h1><p>{{ $note }}</p></div></div>
<section class="ad-card">
    <form method="POST" action="{{ $provider ? route('admin.ai.update', $provider->id) : route('admin.ai.store') }}" class="ad-form" style="max-width: 560px">
        @csrf
        <input type="hidden" name="preset" value="{{ $preset }}">
        <label>Name<input name="name" required maxlength="80" value="{{ old('name', $provider?->name ?? $label) }}"></label>
        @if ($preset === 'custom')
            <label>API address (OpenAI-compatible)<input name="base_url" required placeholder="https://your-server/v1" value="{{ old('base_url', $provider?->base_url) }}"></label>
        @elseif ($baseUrl)
            <label>API address<input value="{{ $baseUrl }}" disabled></label>
        @endif
        <label>Model<input name="model" required maxlength="120" value="{{ old('model', $provider?->model ?? $model) }}"><small class="ad-muted">The provider's model name. The suggested one works on its free tier; change it if the provider renames its models.</small></label>
        <label>API key<input type="password" name="api_key" autocomplete="off" {{ $provider ? '' : 'required' }} placeholder="{{ $provider ? 'Leave empty to keep the saved key' : '' }}"></label>
        <div class="ad-form" style="grid-template-columns: 1fr 1fr; display: grid">
            <label>Input price (USD per million tokens)<input type="number" step="0.0001" min="0" name="price_in" value="{{ old('price_in', $provider?->price_in ?? $prices[0]) }}"></label>
            <label>Output price (USD per million tokens)<input type="number" step="0.0001" min="0" name="price_out" value="{{ old('price_out', $provider?->price_out ?? $prices[1]) }}"></label>
        </div>
        <p class="ad-muted ad-small">0 for free tiers. Prices count towards each store's monthly AI budget.</p>
        @php($suggested = $presets_limits[$preset] ?? [null, null, null, null])
        <h3 style="margin: 8px 0 0">Limits</h3>
        <p class="ad-muted ad-small" style="margin: 0">Growvia counts this provider's use and moves to the next provider before a limit is reached. Empty means no limit. Days and months count in UTC.
            @if (! $provider && array_filter($suggested)) Filled in from the provider's published free-tier limits for the suggested model; check its limits page, as they change. @endif</p>
        <div class="ad-form" style="grid-template-columns: 1fr 1fr; display: grid">
            @foreach ($limits as $field => [$label])
                <label>{{ ucfirst($label) }}<input type="number" min="1" step="1" name="{{ $field }}" placeholder="No limit" value="{{ old($field, $provider ? $provider->{$field} : $suggested[$loop->index]) }}"></label>
            @endforeach
        </div>
        <div><button class="ad-btn primary" type="submit">{{ $provider ? 'Save and test' : 'Add and test' }}</button></div>
    </form>
</section>
@endsection
