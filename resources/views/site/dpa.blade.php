@extends('layouts.site')
@section('title', 'Data Processing Addendum | OrderOrbit')
@section('description', 'OrderOrbit Data Processing Addendum: roles, sub-processors, security measures and breach notification.')
@section('content')
@include('site.partials.legal', ['title' => 'Data Processing Addendum'])
<section class="section tight" style="padding-top:0"><div class="wrap"><div class="prose form-card">
    <h2 style="margin-top:0">Roles</h2>
    <p>The merchant is the controller of shopper personal data. OrderOrbit acts as a processor, processing that data only on the merchant's documented instructions to provide the service.</p>
    <h2>Sub-processors</h2>
    <ul><li>Railway — hosting and database</li><li>Amazon Web Services (SES) — email delivery</li></ul>
    <h2>Security measures</h2>
    <ul><li>Store data isolation and role-based access</li><li>Encrypted secrets and encryption in transit</li><li>Webhook signature validation and audit logs</li><li>Retention controls, export and deletion</li></ul>
    <h2>Breach notification</h2>
    <p>We will notify affected merchants without undue delay after becoming aware of a personal data breach, with the information needed to meet their own obligations.</p>
</div></div></section>
@endsection
