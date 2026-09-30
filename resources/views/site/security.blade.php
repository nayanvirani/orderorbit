@extends('layouts.site')

@section('title', 'Security & Privacy | OrderOrbit Space')
@section('description', 'How OrderOrbit Space protects your store and your customers: Shopify sign-in, minimal permissions, staff roles, audit logs, consent-aware analytics and data deletion.')

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Security &amp; privacy</span>
            <h1>Security and privacy are <em>part of the product.</em></h1>
            <p class="mn-lead">Here's how we protect your store, your team and your customers.</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-wide">
            <h2>Your store</h2>
            <ul class="mn-list">
                <li><b>Secure sign-in through Shopify</b><span>Your team signs in through Shopify admin; there are no separate passwords to manage.</span></li>
                <li><b>Only the permissions the app needs</b><span>We ask Shopify for the minimum access required for the features you use.</span></li>
                <li><b>Roles for your staff</b><span>Owners, admins and staff each see and do only what their role allows.</span></li>
                <li><b>A record of changes</b><span>Publishing, pausing, plan and team changes are logged with who made them.</span></li>
            </ul>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Your customers</h2>
            <ul class="mn-list">
                <li><b>Respects consent</b><span>Analytics only count shoppers who allow it, following your store's privacy settings.</span></li>
                <li><b>No personal data in analytics</b><span>We record views, adds to cart and order totals — not names, emails or addresses.</span></li>
                <li><b>Kept only as long as needed</b><span>Analytics events are deleted automatically after 13 months.</span></li>
                <li><b>Deletion on request</b><span>We handle Shopify's customer and store data requests, and remove your data when you uninstall and request deletion.</span></li>
            </ul>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-narrow mn-prose">
            <h2>Report an issue</h2>
            <p>Found a vulnerability or have a privacy question? Please tell us through the <a href="{{ route('site.contact') }}">contact form</a> with the topic "Support" and we'll respond promptly. You can also read our <a href="{{ route('site.privacy') }}">privacy policy</a> and <a href="{{ route('site.dpa') }}">data processing terms</a>.</p>
        </div>
    </section>
</div>
@endsection
