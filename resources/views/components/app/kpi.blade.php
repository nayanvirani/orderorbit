@props(['label', 'value', 'sub' => null, 'trend' => null, 'spark' => null, 'icon' => null, 'tone' => null])
{{-- KPI tile: $trend is a % change vs the previous period (null hides it); $spark is a list of numbers. --}}
<div {{ $attributes->merge(['class' => 'ob-kpi'.($tone ? ' t-'.$tone : '')]) }}>
    <small>@if ($icon)<x-app.icon :name="$icon" size="sm" />@endif{{ $label }}</small>
    <b>{{ $value }}@if ($trend !== null)<em class="ob-trend {{ $trend > 0.5 ? 'up' : ($trend < -0.5 ? 'down' : 'flat') }}">{{ $trend > 0.5 ? '↑' : ($trend < -0.5 ? '↓' : '→') }} {{ abs(round($trend)) }}%</em>@endif</b>
    @if ($sub)<span>{{ $sub }}</span>@endif
    @if ($spark && count($spark) > 1 && max($spark) > 0)<x-app.sparkline :values="$spark" />@endif
</div>
