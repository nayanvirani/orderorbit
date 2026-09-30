@props(['values' => []])
@php
    $values = array_values($values);
    $n = count($values);
    $max = max(max($values), 0.0001);
    $pts = collect($values)->map(fn ($v, $i) => [round($i / max(1, $n - 1) * 100, 2), round(30 - ($v / $max) * 26, 2)]);
    $line = 'M'.$pts->map(fn ($p) => $p[0].','.$p[1])->implode(' L');
@endphp
<svg class="ob-spark" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true"><path class="area" d="{{ $line }} L100,32 L0,32 Z"/><path class="line" d="{{ $line }}" vector-effect="non-scaling-stroke"/></svg>
