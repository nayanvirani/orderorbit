@extends('errors.layout')
@section('title', 'No access')
@section('code', 'Error 403')
@section('heading')You don't have <em>access</em> here.@endsection
@section('message', 'Your account isn\'t allowed to open this page. If you think it should, ask whoever manages your account, or contact us.')
@section('actions')<a class="btn primary" href="/">Go to homepage</a><a class="btn" href="/contact">Contact us</a>@endsection
