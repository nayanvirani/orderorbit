<div class="faq">
    {{-- A question is [question, answer] with an optional third item: a code sample. --}}
    @foreach ($faqs as $faq)
        @continue(empty($faq[0]))
        <details @if ($loop->first && ($openFirst ?? true)) open @endif>
            <summary>{{ \App\Support\SiteContent::fill($faq[0]) }}<span class="plus"><x-icon name="plus" style="width:14px;height:14px"/></span></summary>
            <p>{{ site_md($faq[1] ?? '') }}</p>
            @if (! empty($faq[2]))<pre><code>{{ $faq[2] }}</code></pre>@endif
        </details>
    @endforeach
</div>
@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_values(array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0] ?? '', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => \App\Support\SiteContent::plain($f[1] ?? '')]], $faqs))], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
