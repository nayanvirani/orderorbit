<section class="section tight">
    <div class="wrap">
        <div class="cta-banner reveal">
            <span class="ring r1"></span><span class="ring r2"></span>
            <h2>{{ $heading ?? 'Convert more customers. Increase order value. Bring customers back.' }}</h2>
            @isset($body)<p>{{ $body }}</p>@endisset
            <div class="ctas">
                <a class="btn lg" href="{{ config('shopify.install_url') }}" data-event="cta_install_clicked"><x-icon name="bag"/>Install on Shopify</a>
                @if (($secondary ?? 'pricing') === 'how')
                    <a class="btn lg ghost-light" href="{{ route('site.how') }}" data-event="cta_how_it_works_clicked">See How It Works</a>
                @else
                    <a class="btn lg ghost-light" href="{{ route('site.pricing') }}">See Pricing</a>
                @endif
            </div>
        </div>
    </div>
</section>
