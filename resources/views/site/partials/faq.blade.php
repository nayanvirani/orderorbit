<div class="faq">
    @foreach ($faqs as [$q, $a])
        <details @if ($loop->first && ($openFirst ?? true)) open @endif>
            <summary>{{ $q }}<span class="plus"><x-icon name="plus" style="width:16px;height:16px"/></span></summary>
            <p>{{ $a }}</p>
        </details>
    @endforeach
</div>
@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
