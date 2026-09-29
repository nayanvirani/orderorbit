@extends('layouts.site')

@section('title', 'Security & Privacy | OrderOrbit')
@section('description', 'Least-privilege Shopify scopes, store data isolation, encrypted secrets, consent-aware analytics, webhook validation and audit logs.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Security</span>
        <h1>Security and privacy are <span class="grad-text">part of the product.</span></h1>
    </div>
</section>
<section class="section tight" style="padding-top:0">
    <div class="wrap grid two">
        @foreach ([
            ['lock', 'Access', ['Shopify OAuth with least-privilege scopes', 'Role-based access for your staff', 'Audit logs']],
            ['shield', 'Data', ['Store data isolation', 'Encrypted secrets', 'Encryption in transit', 'Retention controls', 'Export and deletion on request']],
            ['chart', 'Analytics', ['Collected through Shopify\'s consent-aware Web Pixel', 'Respects the Customer Privacy API', 'Data minimisation']],
            ['zap', 'Reliability', ['Webhook signature validation', 'Idempotent processing', 'Retries and monitoring']],
        ] as [$icon, $title, $items])
            <div class="card reveal">
                <div class="icon-badge"><x-icon :name="$icon"/></div>
                <h3>{{ $title }}</h3>
                <ul class="bullet-list">@foreach ($items as $item)<li><x-icon name="check-circle"/>{{ $item }}</li>@endforeach</ul>
            </div>
        @endforeach
    </div>
    <div class="wrap">
        <div class="note" style="margin-top:28px;background:var(--tint);border-color:#e3deff;color:var(--ink-2)"><x-icon name="alert" style="color:var(--indigo)"/><span><b>Report an issue:</b> found a vulnerability? Please tell us through the <a href="{{ route('site.contact') }}">contact form</a> with the topic "Support" and we'll respond promptly.</span></div>
    </div>
</section>
@include('site.partials.cta')
@endsection
