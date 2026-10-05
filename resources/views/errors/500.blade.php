@extends('errors.layout')
@section('title', 'Something went wrong')
@section('code', 'Error 500')
@section('heading')Something went <em>wrong.</em>@endsection
@section('message', 'An unexpected error happened on our side. Please try again in a moment, and contact us if it keeps happening.')
@section('actions')<a class="btn primary" href="/">Go to homepage</a><a class="btn" href="/contact">Contact us</a>@endsection
