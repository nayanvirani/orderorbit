@extends('layouts.site')

@section('title', 'Help Center | OrderOrbit')
@section('description', 'Setup guides, product docs and troubleshooting for OrderOrbit.')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow"><span class="dot"></span>Help center</span>
        <h1>How can we <span class="grad-text">help?</span></h1>
        <p class="lead">Setup guides, product docs and troubleshooting for OrderOrbit.</p>
        <label class="search"><x-icon name="search"/><input type="search" placeholder="Search help topics…" aria-label="Search help topics" data-help-search></label>
    </div>
</section>
<section class="section tight" style="padding-top:0">
    <div class="wrap">
        <div class="grid four">
            @foreach ($helpCategories as $c)
                <div class="card hover" id="{{ $c['slug'] }}" data-help-item>
                    <div class="icon-badge soft"><x-icon :name="$c['icon']"/></div>
                    <h3 style="font-size:17px">{{ $c['name'] }}</h3>
                    <p>{{ $c['text'] }}</p>
                </div>
            @endforeach
        </div>
        <div class="card center" data-help-empty hidden style="margin-top:20px">
            <h3>We couldn't find anything for "<span data-help-q></span>".</h3>
            <p>Try different words or <a href="{{ route('site.contact') }}">contact support</a>.</p>
        </div>
        <div class="note" style="margin-top:32px;background:var(--tint);border-color:#e3deff;color:var(--ink-2)"><x-icon name="message" style="color:var(--indigo)"/><span>Still stuck? Open a ticket from the app — it includes your store details automatically — or <a href="{{ route('site.contact') }}">contact us</a>.</span></div>
    </div>
</section>
@endsection
