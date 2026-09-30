@extends('layouts.site')
@section('title', 'Terms of Service | OrderOrbit Space')
@section('description', 'Terms of Service for OrderOrbit Space, the Shopify CRO, checkout and customer experience app.')
@section('content')
@include('site.partials.legal', ['title' => 'Terms of Service'])
<section class="section tight" style="padding-top:0"><div class="wrap"><div class="prose form-card">
    <h2 style="margin-top:0">The service</h2>
    <p>OrderOrbit Space provides CRO experiences, checkout and customer-account blocks, lifecycle automation, analytics, A/B testing and personalization for Shopify stores.</p>
    <h2>Billing through Shopify</h2>
    <p>Plans are billed monthly through your Shopify invoice. Plans, limits and prices are listed on the <a href="{{ route('site.pricing') }}">Pricing</a> page. On downgrade, existing data is kept and items over the new plan's limits are paused, not removed.</p>
    <h2>Acceptable use</h2>
    <p>You agree not to use OrderOrbit Space for deceptive practices, including fake urgency, fabricated reviews or misleading offers, or in breach of Shopify's terms or applicable law.</p>
    <h2>Liability</h2>
    <p>Attribution and analytics are analytical models, not guarantees of results. OrderOrbit Space is provided as is, to the extent permitted by law.</p>
    <h2>Termination</h2>
    <p>You can cancel any time by uninstalling the app. We may suspend accounts that breach these terms.</p>
</div></div></section>
@endsection
