@extends('admin.layout')
@section('title', 'Sign in')
@section('content')
<form method="POST" action="{{ route('admin.login') }}" class="ad-login">
    @csrf
    <h1>OrderOrbit Space Admin</h1>
    <p class="ad-muted">For the OrderOrbit team only.</p>
    <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <label class="ad-check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
    @error('email')<p class="ad-error">{{ $message }}</p>@enderror
    <button type="submit" class="ad-btn primary">Sign in</button>
</form>
@endsection
