@extends('layouts.site')

@section('title', 'Contact | OrderOrbit')
@section('description', 'Questions about OrderOrbit, pricing or partnerships? We reply within one business day.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Contact</span>
        <h1>Let's talk about <span class="grad-text">growing your store.</span></h1>
        <p class="lead">Questions about OrderOrbit, pricing or partnerships? We reply within one business day.</p>
    </div>
</section>
<section class="section tight" style="padding-top:0">
    <div class="wrap split" style="align-items:start">
        <div class="form-card">
            @if (session('contact_sent'))
                <div class="alert ok">Thanks — we've got your message and will reply within one business day.</div>
            @endif
            @if (session('contact_failed'))
                <div class="alert bad">We couldn't send your message. Please try again or email support.</div>
            @endif
            <form method="POST" action="{{ route('site.contact.submit') }}" data-event-form="contact_submitted" novalidate>
                @csrf
                <div class="form-grid">
                    <label class="fld">Name*<input name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
                    <label class="fld">Work email*<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
                    <label class="fld">Company<input name="company" value="{{ old('company') }}" autocomplete="organization"></label>
                    <label class="fld">Shopify store URL<input name="store_url" value="{{ old('store_url') }}" placeholder="yourstore.myshopify.com">@error('store_url')<span class="err">{{ $message }}</span>@enderror</label>
                    <label class="fld full">Topic*
                        <select name="topic" required>
                            @foreach (['Sales', 'Support', 'Partnership', 'Other'] as $topic)<option @selected(old('topic') === $topic)>{{ $topic }}</option>@endforeach
                        </select>
                    </label>
                    <label class="fld full">Message*<textarea name="message" required>{{ old('message') }}</textarea>@error('message')<span class="err">{{ $message }}</span>@enderror</label>
                    <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="full"><button class="btn primary lg" type="submit" data-event="contact_submitted">Send Message <x-icon name="arrow"/></button></div>
                </div>
            </form>
        </div>
        <div class="stack">
            <div class="card">
                <div class="icon-badge"><x-icon name="message"/></div>
                <h3>Already a customer?</h3>
                <p>The fastest way to get help is Support inside the app — it includes your store details automatically.</p>
            </div>
            <div class="card">
                <div class="icon-badge soft"><x-icon name="help"/></div>
                <h3>Looking for answers?</h3>
                <p>Setup guides and troubleshooting live in the Help Center.</p>
                <a class="more" href="{{ route('site.help') }}" style="text-decoration:none">Visit Help Center <x-icon name="arrow"/></a>
            </div>
        </div>
    </div>
</section>
@endsection
