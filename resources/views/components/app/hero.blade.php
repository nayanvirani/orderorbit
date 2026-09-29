@props(['eyebrow' => null, 'title', 'lead' => null])
{{-- Branded page hero (public site identity inside the Polaris app). $title may contain <em> for the gradient accent. --}}
<section {{ $attributes->merge(['class' => 'ob-hero']) }}>
    @if ($eyebrow)<span class="ob-eyebrow">{{ $eyebrow }}</span>@endif
    <h1 class="ob-title">{!! $title !!}</h1>
    @if ($lead)<p class="ob-lead">{{ $lead }}</p>@endif
    @if (trim($slot) !== '')<div class="ob-actions">{{ $slot }}</div>@endif
</section>
