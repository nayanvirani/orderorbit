@extends('errors.layout')
@section('title', 'Too many requests')
@section('code', 'Error 429')
@section('heading')Slow down a <em>little.</em>@endsection
@section('message', 'We received a lot of requests from you in a short time. Wait a minute, then try again.')
@section('actions')<a class="btn primary" href="/">Go to homepage</a>@endsection
