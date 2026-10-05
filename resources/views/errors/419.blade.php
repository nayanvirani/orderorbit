@extends('errors.layout')
@section('title', 'Page expired')
@section('code', 'Error 419')
@section('heading')This page <em>expired.</em>@endsection
@section('message', 'For your security, forms expire after a while. Go back, refresh the page and try again.')
@section('actions')<a class="btn primary" href="javascript:history.back()">Go back</a><a class="btn" href="/">Go to homepage</a>@endsection
