@php
    $stages = [
        'create' => ['Create', 'Pick a template', 'sparkle', 50, 9],
        'publish' => ['Publish', 'Place in Theme Editor', 'rocket', 85.5, 29.5],
        'measure' => ['Measure', 'Web Pixel events', 'chart', 85.5, 70.5],
        'test' => ['Test', 'A/B and A/B/C', 'split', 50, 91],
        'personalize' => ['Personalize', 'Segments & rules', 'target', 14.5, 70.5],
        'automate' => ['Automate', 'Lifecycle workflows', 'flow', 14.5, 29.5],
    ];
    $active = $active ?? array_keys($stages);
@endphp
<div class="orbit {{ ($light ?? false) ? 'light' : '' }}" role="img" aria-label="The OrderOrbit loop: Create, Publish, Measure, Test, Personalize, Automate">
    <svg class="rings" viewBox="0 0 100 100" aria-hidden="true">
        <defs>
            <linearGradient id="ring-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5b4bff"/><stop offset=".5" stop-color="#8f5cff"/><stop offset="1" stop-color="#ff5ca8"/></linearGradient>
        </defs>
        <circle cx="50" cy="50" r="48" fill="none" stroke="{{ ($light ?? false) ? '#e7e5f3' : 'rgba(255,255,255,.06)' }}" stroke-width=".3"/>
        <g class="spin"><circle cx="50" cy="50" r="41" fill="none" stroke="url(#ring-grad)" stroke-width=".5" stroke-dasharray="1.2 2.2" opacity=".9"/></g>
        <g class="spin rev"><circle cx="50" cy="50" r="27" fill="none" stroke="{{ ($light ?? false) ? '#d9d6ec' : 'rgba(255,255,255,.12)' }}" stroke-width=".3" stroke-dasharray=".6 1.6"/></g>
        <circle r="1.4" fill="#ff5ca8"><animateMotion dur="12s" repeatCount="indefinite" path="M50,9 a41,41 0 1,1 -0.01,0"/></circle>
        <circle r="1" fill="#fff" opacity=".9"><animateMotion dur="12s" begin="-6s" repeatCount="indefinite" path="M50,9 a41,41 0 1,1 -0.01,0"/></circle>
    </svg>
    <div class="core"><div><strong>OrderOrbit</strong><small>one loop for growth</small></div></div>
    @foreach ($stages as $key => [$label, $sub, $icon, $x, $y])
        <div class="node {{ in_array($key, $active, true) ? 'on' : 'off' }}" style="left:{{ $x }}%;top:{{ $y }}%">
            <span class="bubble"><x-icon :name="$icon"/></span>
            <b>{{ $label }}</b>
            <span>{{ $sub }}</span>
        </div>
    @endforeach
</div>
