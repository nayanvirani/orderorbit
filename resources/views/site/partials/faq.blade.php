<div class="faq">
    {{-- An article is [question, answer] with an optional third item: a code sample. --}}
    @foreach ($faqs as $faq)
        <details @if ($loop->first && ($openFirst ?? true)) open @endif>
            <summary>{{ $faq[0] }}<span class="plus"><x-icon name="plus" style="width:16px;height:16px"/></span></summary>
            <p>{{ $faq[1] }}</p>
            @isset($faq[2])<pre class="code"><code>{{ $faq[2] }}</code></pre>@endisset
        </details>
    @endforeach
</div>
@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
