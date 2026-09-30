@props(['title', 'text' => null])
<div class="ob-empty">
    <svg viewBox="0 0 120 90" aria-hidden="true">
        <ellipse cx="60" cy="80" rx="42" ry="6" fill="#eef2ff"/>
        <ellipse cx="60" cy="44" rx="50" ry="16" fill="none" stroke="#c7c9f7" stroke-width="2" stroke-dasharray="4 5" transform="rotate(-14 60 44)"/>
        <rect x="38" y="24" width="44" height="40" rx="10" fill="#4f46e5"/>
        <path d="M38 38h44" stroke="#7c74ff" stroke-width="3"/>
        <rect x="54" y="20" width="12" height="16" rx="3" fill="#a5a0ff"/>
        <circle cx="103" cy="30" r="6" fill="#f59e0b"/>
        <circle cx="16" cy="56" r="4" fill="#10b981"/>
        <path d="M96 62l3 3 5-6" stroke="#10b981" stroke-width="2.5" fill="none" stroke-linecap="round"/>
    </svg>
    <h3>{{ $title }}</h3>
    @if ($text)<p>{{ $text }}</p>@endif
    {{ $slot }}
</div>
