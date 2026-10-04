@php($me = request()->attributes->get('storeUser'))
<nav class="oo-tabs" aria-label="Settings">
    @foreach ([
        ['app.settings.store', 'Store', null],
        ['app.settings.branding', 'Branding', null],
        ['app.settings.users', 'Users & roles', 'manage_users'],
        ['app.settings.billing', 'Billing', null],
        ['app.settings.integrations', 'Integrations', null],
        ['app.settings.privacy', 'Privacy', null],
        ['app.settings.activity', 'Activity', 'view_activity'],
    ] as [$route, $label, $permission])
        @if ($permission === null || $me?->can($permission))
            <a href="{{ app_route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
        @endif
    @endforeach
</nav>
