@props(['title', 'text' => null])
<div class="ob-empty">
    <svg viewBox="0 0 64 64" aria-hidden="true">
        <defs><linearGradient id="ob-e" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1a1a1a"/><stop offset="1" stop-color="#1a1a1a"/></linearGradient></defs>
        <ellipse cx="32" cy="32" rx="29" ry="12" fill="none" stroke="url(#ob-e)" stroke-width="2" transform="rotate(-24 32 32)"/>
        <circle cx="32" cy="32" r="12" fill="url(#ob-e)"/>
        <circle cx="55" cy="21" r="4" fill="#1a1a1a"/>
    </svg>
    <h3>{{ $title }}</h3>
    @if ($text)<p>{{ $text }}</p>@endif
    {{ $slot }}
</div>
