@extends('layouts.site')
@section('title', 'Privacy Policy | OrderOrbit')
@section('description', 'How OrderOrbit collects, uses, retains and deletes merchant and shopper data, including Web Pixel analytics.')
@section('content')
@include('site.partials.legal', ['title' => 'Privacy Policy'])
<section class="section tight" style="padding-top:0"><div class="wrap"><div class="prose form-card">
    <h2 style="margin-top:0">Data we collect from merchants</h2>
    <p>When you install OrderOrbit we store your shop domain, shop name, contact email, currency, timezone, Shopify plan, the access token Shopify issues to the app, and your staff's Shopify user IDs and roles. We also store the experiences, workflows, experiments and settings you create.</p>
    <h2>Data collected through the Web Pixel</h2>
    <p>On storefronts where OrderOrbit is enabled, our Shopify Web Pixel collects shopping events (for example page and product views, add-to-cart, checkout started and checkout completed) and interactions with OrderOrbit experiences. Events include a session and visitor identifier, page type, product and variant, device, market or country where appropriate, experiment assignment and UTM parameters.</p>
    <h2>Purpose</h2>
    <p>We use this data to run the experiences you configure, measure their performance, run A/B tests, build the segments you define, execute your automation workflows and provide support.</p>
    <h2>Consent</h2>
    <p>Analytics respect your store's consent settings through Shopify's Customer Privacy API. Where a shopper has not consented, we do not collect analytics events for them.</p>
    <h2>Sub-processors</h2>
    <ul><li>Hosting and database infrastructure (Railway)</li><li>Email delivery (Amazon SES)</li><li>Shopify, as the platform the app runs on</li></ul>
    <h2>Retention</h2>
    <p>You choose an event retention period in Settings → Privacy. Configuration data is kept while the app is installed. After uninstall, Shopify asks us to delete store data and we do so.</p>
    <h2>Your rights (GDPR / CCPA)</h2>
    <p>Merchants can export or delete their data from Settings → Privacy. Shoppers can exercise their rights through the merchant; we process Shopify's mandatory privacy webhooks — customer data request, customer redact and shop redact — for every store.</p>
    <h2>Contact</h2>
    <p>Questions about privacy can be sent through our <a href="{{ route('site.contact') }}">contact page</a>.</p>
</div></div></section>
@endsection
