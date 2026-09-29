@extends('layouts.site')
@section('title', 'Page not found | OrderOrbit')
@section('content')
<section class="page-hero sky" style="padding:96px 0">
    <div class="wrap">
        <div style="max-width:320px;margin:0 auto 12px">@include('site.diagrams.orbit', ['light' => true, 'active' => []])</div>
        <h1>This page drifted <span class="grad-text">out of orbit.</span></h1>
        <p class="lead">The link may be broken or the page moved.</p>
        <div class="ctas">
            <a class="btn primary lg" href="{{ route('site.home') }}">Go to homepage</a>
            <a class="btn lg" href="{{ route('site.help') }}">Visit Help Center</a>
        </div>
    </div>
</section>
@endsection
