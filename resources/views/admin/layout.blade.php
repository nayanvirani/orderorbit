<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · OrderOrbit Space Admin</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
@auth
    <header class="ad-top">
        <a class="ad-brand" href="{{ route('admin.home') }}">OrderOrbit Space <span>Admin</span></a>
        <nav class="ad-nav" aria-label="Admin">
            @foreach (['admin.home' => 'Overview', 'admin.stores' => 'Stores', 'admin.tickets' => 'Support', 'admin.failures' => 'Workflow failures', 'admin.analytics' => 'Analytics', 'admin.templates' => 'Templates', 'admin.flags' => 'Feature flags', 'admin.audit' => 'Audit log'] as $route => $label)
                <a href="{{ route($route) }}" @if (request()->routeIs($route.'*')) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}" class="ad-user">@csrf<span>{{ auth()->user()->email }}</span><button type="submit">Sign out</button></form>
    </header>
@endauth
<main class="ad-main">
    @if (session('status'))<div class="ad-flash">{{ session('status') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
