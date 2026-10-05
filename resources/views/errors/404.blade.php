@extends('errors.layout')
@section('title', 'Page not found')
@section('code', 'Error 404')
@section('heading')This page drifted <em>out of orbit.</em>@endsection
@section('message', 'The link may be broken, or the page has moved.')
@section('actions')<a class="btn primary" href="/">Go to homepage</a><a class="btn" href="/help">Visit Help Center</a>@endsection
