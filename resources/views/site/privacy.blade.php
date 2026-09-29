@extends('layouts.site')

@section('title', 'Privacy Policy | OrderOrbit')

@section('content')
<div class="prose">
    <h1>Privacy Policy</h1>
    <p class="muted">Draft — to be finalised before App Store launch.</p>
    <h2>Data we collect</h2>
    <p>When a merchant installs OrderOrbit we store the shop domain, shop name, contact email, currency, timezone and the access token Shopify issues to the app.</p>
    <p>On storefronts where the merchant enables OrderOrbit, our Shopify Web Pixel collects shopping events (for example product views, add-to-cart and checkout completion) and interactions with OrderOrbit experiences. Collection respects the store's Shopify customer privacy and consent settings.</p>
    <h2>Retention and deletion</h2>
    <p>Merchants choose an event retention period. We process Shopify's customer data request, customer redaction and shop redaction webhooks, and delete a store's data after uninstall when Shopify requests it.</p>
    <h2>Contact</h2>
    <p>Questions about privacy can be sent to the OrderOrbit team through the Contact page.</p>
</div>
@endsection
