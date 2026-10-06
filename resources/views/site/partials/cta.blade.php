{{-- Call-to-action band: $cta = ['title', 'text', 'primary' => [label, href], 'secondary' => [label, href]] from Website content. --}}
<section class="section {{ $class ?? 'top-0' }}">
    <div class="wrap">
        <div class="cta-band reveal">
            <div>
                <h2>{{ site_md($cta['title'] ?? '', $vars ?? []) }}</h2>
                @if (! empty($cta['text']))<p>{{ site_md($cta['text'], $vars ?? []) }}</p>@endif
            </div>
            <div class="row">
                @if (! empty($cta['primary']['label']))<a class="btn light" href="{{ site_url($cta['primary']['href']) }}" data-event="cta_clicked">{{ $cta['primary']['label'] }}</a>@endif
                @if (! empty($cta['secondary']['label']))<a class="btn ghost" href="{{ site_url($cta['secondary']['href']) }}">{{ $cta['secondary']['label'] }}</a>@endif
            </div>
        </div>
    </div>
</section>
