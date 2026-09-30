@props(['eyebrow' => null, 'title', 'lead' => null, 'icon' => null, 'tone' => null])
{{-- Page intro: optional feature icon, title (may contain <em> for the accent), lead and actions. --}}
<section {{ $attributes->merge(['class' => 'ob-hero'.($tone ? ' t-'.$tone : '')]) }}>
    @if ($icon)<x-app.icon :name="$icon" size="lg" />@endif
    <div class="ob-hero-main">
        @if ($eyebrow)<span class="ob-eyebrow">{{ $eyebrow }}</span>@endif
        <h1 class="ob-title">{!! $title !!}</h1>
        @if ($lead)<p class="ob-lead">{{ $lead }}</p>@endif
        @if (trim($slot) !== '')<div class="ob-actions">{{ $slot }}</div>@endif
    </div>
</section>
