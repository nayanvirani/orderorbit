@extends('layouts.site')

@section('title', 'Contact | OrderOrbit Space')
@section('description', 'Questions about OrderOrbit Space, pricing or partnerships? We reply within one business day.')

@section('content')
<div class="mn">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Contact</span>
            <h1>Talk to <em>a real person.</em></h1>
            <p class="mn-lead">Questions about features, pricing, early access or partnerships? Send a message and we'll reply within one business day.</p>
        </div>
    </section>

    <section class="mn-section plain">
        <div class="mn-narrow">
            <div class="form-card">
                @if (session('contact_sent'))
                    <div class="alert ok">Thanks — we've got your message and will reply within one business day.</div>
                @endif
                @if (session('contact_failed'))
                    <div class="alert bad">We couldn't send your message. Please try again in a moment.</div>
                @endif
                <form method="POST" action="{{ route('site.contact.submit') }}" data-event-form="contact_submitted" novalidate>
                    @csrf
                    <div class="form-grid">
                        <label class="fld">Name*<input name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
                        <label class="fld">Email*<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
                        <label class="fld">Company<input name="company" value="{{ old('company') }}" autocomplete="organization"></label>
                        <label class="fld">Shopify store URL<input name="store_url" value="{{ old('store_url') }}" placeholder="yourstore.myshopify.com">@error('store_url')<span class="err">{{ $message }}</span>@enderror</label>
                        <label class="fld full">Topic*
                            <select name="topic" required>
                                @foreach (['Sales', 'Support', 'Partnership', 'Other'] as $topic)<option @selected(old('topic') === $topic)>{{ $topic }}</option>@endforeach
                            </select>
                        </label>
                        <label class="fld full">Message*<textarea name="message" required>{{ old('message') }}</textarea>@error('message')<span class="err">{{ $message }}</span>@enderror</label>
                        <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                        <div class="full"><button class="btn primary lg" type="submit" data-event="contact_submitted">Send message</button></div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="mn-section">
        <div class="mn-wide">
            <h2>Other ways to get help</h2>
            <ul class="mn-list">
                <li><b>Already a customer?</b><span>Support inside the app is the fastest route — it includes your store details, so we can look into things straight away.</span></li>
                <li><b>Looking for a quick answer?</b><span>The <a href="{{ route('site.help') }}">Help Center</a> covers setup, templates, bundles, gifts, analytics and billing.</span></li>
                <li><b>Want an upcoming feature?</b><span>Tell us about your store and which feature you're waiting for, and we'll let you know when early access opens.</span></li>
            </ul>
        </div>
    </section>
</div>
@endsection
