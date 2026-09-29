@extends('layouts.site')

@section('title', 'Pricing | OrderOrbit')
@section('description', 'Starter, Growth and Scale plans for OrderOrbit, billed through Shopify.')

@section('content')
<section class="block">
    <div class="wrap">
        <h1 style="text-align:center">Plans that grow with your store.</h1>
        @include('site.partials.plans')
    </div>
</section>
@endsection
