@extends('layouts.site')
@section('title', 'Data Processing Addendum | OrderOrbit Space')
@section('description', 'OrderOrbit Space Data Processing Addendum: roles, security measures and breach notification.')
@section('content')
@include('site.partials.legal', ['title' => 'Data Processing Addendum'])
<section class="section tight" style="padding-top:0"><div class="wrap"><div class="prose form-card">
    <h2 style="margin-top:0">Roles</h2>
    <p>The merchant is the controller of shopper personal data. OrderOrbit Space acts as a processor, processing that data only on the merchant's documented instructions to provide the service.</p>
    <h2>Security measures</h2>
    <ul><li>Each store's data is kept separate, with role-based access for staff</li><li>Sensitive information is protected at all times</li><li>Every update from Shopify is verified, and important account activity is recorded</li><li>Retention controls, export and deletion</li></ul>
    <h2>Breach notification</h2>
    <p>We will notify affected merchants without undue delay after becoming aware of a personal data breach, with the information needed to meet their own obligations.</p>
</div></div></section>
@endsection
