{{-- Section sidebar for every module with several pages (CRO has its own, richer one). --}}
@php
    $tab = request('tab');
    $modules = [
        'automation' => ['label' => 'Automation', 'icon' => 'bolt', 'items' => [
            ['Workflows', 'bolt', 'app.automation.index', [], ['app.automation.index', 'app.automation.edit']],
            ['Templates', 'palette', 'app.automation.templates', [], ['app.automation.templates']],
            ['Runs', 'play', 'app.automation.runs', [], ['app.automation.runs', 'app.automation.runs.show']],
            ['Inbox', 'inbox', 'app.automation.inbox', [], ['app.automation.inbox']],
            ['Emails', 'mail', 'app.automation.emails', [], ['app.automation.emails']],
        ]],
        'experiments' => ['label' => 'A/B tests', 'icon' => 'split', 'items' => [
            ['Active tests', 'play', 'app.experiments.index', ['tab' => 'active'], fn () => request()->routeIs('app.experiments.index') && in_array($tab, [null, 'active'], true) || request()->routeIs('app.experiments.show')],
            ['Drafts', 'draft', 'app.experiments.index', ['tab' => 'drafts'], fn () => request()->routeIs('app.experiments.index') && $tab === 'drafts' || request()->routeIs('app.experiments.edit')],
            ['Completed', 'check', 'app.experiments.index', ['tab' => 'completed'], fn () => request()->routeIs('app.experiments.index') && $tab === 'completed'],
        ]],
        'audiences' => ['label' => 'Audiences', 'icon' => 'users', 'items' => [
            ['Segments', 'users', 'app.audiences.segments', [], ['app.audiences.segments', 'app.audiences.segments.*']],
            ['Personalization rules', 'rules', 'app.audiences.rules', [], ['app.audiences.rules', 'app.audiences.rules.*']],
        ]],
        'analytics' => ['label' => 'Analytics', 'icon' => 'chart', 'items' => [
            ['Overview', 'chart', 'app.analytics', [], ['app.analytics']],
            ['Events', 'activity', 'app.analytics.events', [], ['app.analytics.events']],
            ['Funnels', 'funnel', 'app.analytics.funnels', [], ['app.analytics.funnels', 'app.analytics.funnel']],
            ['Revenue & attribution', 'tag', 'app.analytics.revenue', [], ['app.analytics.revenue']],
            ['Customer journeys', 'route', 'app.analytics.journeys', [], ['app.analytics.journeys', 'app.analytics.journey']],
        ]],
        'settings' => ['label' => 'Settings', 'icon' => 'settings', 'items' => [
            ['Store', 'store', 'app.settings.store', [], ['app.settings.store']],
            ['Branding', 'palette', 'app.settings.branding', [], ['app.settings.branding']],
            ['Users & roles', 'users', 'app.settings.users', [], ['app.settings.users'], 'manage_users'],
            ['Billing', 'card', 'app.settings.billing', [], ['app.settings.billing']],
            ['Integrations', 'plug', 'app.settings.integrations', [], ['app.settings.integrations']],
            ['Privacy', 'lock', 'app.settings.privacy', [], ['app.settings.privacy']],
            ['Activity', 'list', 'app.settings.activity', [], ['app.settings.activity'], 'view_activity'],
        ]],
    ];
    $module = $modules[$section];
@endphp
<aside class="ob-sidebar">
    <nav class="ob-subnav" aria-label="{{ $module['label'] }}">
        <p class="ob-side-head" style="margin-top:4px">{{ $module['label'] }}</p>
        @foreach ($module['items'] as $item)
            @php([$label, $icon, $route, $params, $active] = $item)
            @continue(isset($item[5]) && ! request()->attributes->get('storeUser')?->can($item[5]))
            @php($on = is_callable($active) ? $active() : request()->routeIs(...$active))
            <a href="{{ app_route($route, $params) }}" @if ($on) aria-current="page" @endif>
                <x-app.icon :name="$icon" size="sm" tone="default"/><span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</aside>
