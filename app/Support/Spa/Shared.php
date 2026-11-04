<?php

namespace App\Support\Spa;

use App\Experiences\Registry;
use App\Models\Experience;
use App\Models\Store;
use App\Support\Permissions;
use Illuminate\Http\Request;

/**
 * Data every React page gets: who's signed in, what they may do, the plan, the section
 * sidebar (with the current item worked out here, from the route) and the sales-limit notice.
 */
class Shared
{
    public static function for(Request $request): array
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('store');
        $user = $request->attributes->get('storeUser');
        $access = (bool) $store?->hasPlanAccess();

        return [
            'shop' => $store?->shop_domain,
            'storeName' => $store?->name,
            'currency' => $store?->currency ?? 'USD',
            'user' => $user ? ['name' => $user->first_name, 'role' => $user->role] : null,
            'can' => collect(array_keys(Permissions::MATRIX))->mapWithKeys(fn ($p) => [$p => (bool) $user?->can($p)])->all(),
            'plan' => $store?->effectivePlan(),
            // Features this store can use (a child feature counts only with its parent).
            'includes' => $store ? array_values(array_filter(array_keys(\App\Support\Modules::ALL), fn ($k) => $store->planIncludes($k))) : [],
            // For locked features: the first plan that includes each one, for "Upgrade to Growth".
            'featurePlans' => $store ? \App\Support\Plans::firstWith() : [],
            'featureLabels' => collect(\App\Support\Modules::all())->map(fn ($m) => $m[0])->all(),
            'access' => (bool) $access,
            'goal' => (bool) $store?->goal,
            'adminUrl' => $store?->adminUrl(),
            'nav' => $access ? self::nav($request, $store) : null,
            'limit' => $access && ! $request->routeIs('app.settings.billing') ? self::limit($store) : null,
            'legal' => $user ? \App\Support\Legal::noticeFor($store) : null,
            // Announcements from the Growvia team (G41) for this person and page.
            'announcements' => $store && $user ? \App\Support\Announcements::forPage($store, $user, $request->routeIs('app.dashboard')) : [],
            // The live chat button (G43), released with the live_chat flag.
            'chat' => $store && $user && \App\Support\Features::enabled('live_chat', $store),
            // The new menu and offer names (flag new_menu).
            'newMenu' => $store && Registry::newMenu(),
        ];
    }

    private static function nav(Request $request, Store $store): ?array
    {
        if ($request->routeIs('app.cro.*', 'app.bundles.*', 'app.gifts.*', 'app.features.*')) {
            return ['section' => 'cro', 'label' => Registry::newMenu() ? 'Offers' : 'CRO', 'groups' => self::croGroups($request, $store)];
        }

        $tab = $request->query('tab');
        $user = $request->attributes->get('storeUser');
        $is = fn (...$names) => fn () => $request->routeIs(...$names);
        $modules = [
            'automation' => ['Automation', [
                ['Workflows', 'bolt', 'app.automation.index', [], $is('app.automation.index', 'app.automation.edit')],
                ['Templates', 'palette', 'app.automation.templates', [], $is('app.automation.templates')],
                ['Runs', 'play', 'app.automation.runs', [], $is('app.automation.runs', 'app.automation.runs.show')],
                ['Inbox', 'inbox', 'app.automation.inbox', [], $is('app.automation.inbox')],
                ['Emails', 'mail', 'app.automation.emails', [], $is('app.automation.emails')],
            ]],
            'experiments' => ['A/B tests', [
                ['Active tests', 'play', 'app.experiments.index', ['tab' => 'active'], fn () => $request->routeIs('app.experiments.index') && in_array($tab, [null, 'active'], true) || $request->routeIs('app.experiments.show')],
                ['Drafts', 'draft', 'app.experiments.index', ['tab' => 'drafts'], fn () => $request->routeIs('app.experiments.index') && $tab === 'drafts' || $request->routeIs('app.experiments.edit')],
                ['Completed', 'check', 'app.experiments.index', ['tab' => 'completed'], fn () => $request->routeIs('app.experiments.index') && $tab === 'completed'],
            ]],
            'audiences' => ['Audiences', [
                ['Segments', 'users', 'app.audiences.segments', [], $is('app.audiences.segments', 'app.audiences.segments.*', 'app.audiences')],
                ['Personalization rules', 'rules', 'app.audiences.rules', [], $is('app.audiences.rules', 'app.audiences.rules.*')],
            ]],
            'analytics' => ['Analytics', [
                ['Overview', 'chart', 'app.analytics', [], $is('app.analytics')],
                ['Events', 'activity', 'app.analytics.events', [], $is('app.analytics.events')],
                ['Funnels', 'funnel', 'app.analytics.funnels', [], $is('app.analytics.funnels', 'app.analytics.funnel')],
                ['Revenue & attribution', 'tag', 'app.analytics.revenue', [], $is('app.analytics.revenue')],
                ['Customer journeys', 'route', 'app.analytics.journeys', [], $is('app.analytics.journeys', 'app.analytics.journey')],
            ]],
            'settings' => ['Settings', [
                ['Store', 'store', 'app.settings.store', [], $is('app.settings.store')],
                ['Branding', 'palette', 'app.settings.branding', [], $is('app.settings.branding')],
                ['Users & roles', 'users', 'app.settings.users', [], $is('app.settings.users'), 'manage_users'],
                ['Billing', 'card', 'app.settings.billing', [], $is('app.settings.billing')],
                ['Integrations', 'plug', 'app.settings.integrations', [], $is('app.settings.integrations')],
                ['Privacy', 'lock', 'app.settings.privacy', [], $is('app.settings.privacy')],
                ['Activity', 'list', 'app.settings.activity', [], $is('app.settings.activity'), 'view_activity'],
            ]],
        ];
        $section = match (true) {
            $request->routeIs('app.automation.*') => 'automation',
            $request->routeIs('app.experiments.*') => 'experiments',
            $request->routeIs('app.audiences', 'app.audiences.*') => 'audiences',
            $request->routeIs('app.analytics', 'app.analytics.*') => 'analytics',
            $request->routeIs('app.settings.*') => 'settings',
            default => null,
        };
        if (! $section) {
            return null;
        }
        [$label, $items] = $modules[$section];
        $new = Registry::newMenu();
        if ($new) {
            // The new menu (flag new_menu): friendlier names; A/B tests and Audiences are one item.
            $rename = ['Overview' => 'Widget results', 'Revenue & attribution' => 'Attribution', 'Personalization rules' => 'Personalization',
                'Users & roles' => 'Team and permissions', 'Billing' => 'Plan and billing', 'Activity' => 'Activity log'];
            $items = array_map(fn ($i) => [$rename[$i[0]] ?? $i[0], ...array_slice($i, 1)], $items);
            $label = ['analytics' => 'Insights', 'experiments' => 'Audiences and tests', 'audiences' => 'Audiences and tests'][$section] ?? $label;
            if (in_array($section, ['experiments', 'audiences'], true)) {
                $tests = array_map(fn ($i) => [$i[0] === 'Active tests' ? 'Active tests' : $i[0], ...array_slice($i, 1)], $modules['experiments'][1]);
                $audiences = array_map(fn ($i) => [$rename[$i[0]] ?? $i[0], ...array_slice($i, 1)], $modules['audiences'][1]);
                $row = fn ($i) => ['label' => $i[0], 'icon' => $i[1], 'tone' => 'default', 'href' => self::path($i[2], $i[3]), 'active' => (bool) $i[4]()];

                return ['section' => 'tests', 'label' => $label, 'groups' => [
                    ['heading' => 'A/B tests', 'items' => array_map($row, $tests)],
                    ['heading' => 'Audiences', 'items' => array_map($row, $audiences)],
                ]];
            }
        }
        // The automation redesign renames three items (labels only), released with the flow_editor flag.
        if ($section === 'automation' && ($new || \App\Support\Features::enabled('flow_editor', $store))) {
            $rename = ['Runs' => 'Run history', 'Inbox' => 'Team inbox', 'Emails' => 'Emails sent'];
            $items = array_map(fn ($i) => [$rename[$i[0]] ?? $i[0], ...array_slice($i, 1)], $items);
        }
        $items = array_values(array_filter($items, fn ($i) => ! isset($i[5]) || $user?->can($i[5])));

        return ['section' => $section, 'label' => $label, 'groups' => [['heading' => null, 'items' => array_map(fn ($i) => [
            'label' => $i[0], 'icon' => $i[1], 'tone' => 'default', 'href' => self::path($i[2], $i[3]), 'active' => (bool) $i[4](),
        ], $items)]]];
    }

    private static function croGroups(Request $request, Store $store): array
    {
        $features = Registry::features();
        $feature = $request->route('feature');
        // On an experience's own pages, highlight the feature it belongs to rather than "All offers".
        $experience = $request->route('experience');
        $experienceId = $experience instanceof Experience ? $experience->id : (is_numeric($experience) ? (int) $experience : null);
        $experienceFeature = $experienceId
            ? Registry::featureFor((string) Experience::whereKey($experienceId)->where('store_id', $store->id)->value('type'))
            : null;
        if (! $experienceFeature && $request->routeIs('app.cro.experiences.create') && Registry::has($request->query('type'))) {
            $experienceFeature = Registry::featureFor($request->query('type'));
        }
        $feature ??= $experienceFeature;

        $link = fn (string $key) => [
            'label' => $features[$key]['label'],
            'icon' => $features[$key]['icon'] ?? 'sparkle',
            'tone' => $features[$key]['tone'] ?? null,
            'href' => isset($features[$key]['module']) ? self::path($features[$key]['module']) : self::path('app.features.show', ['feature' => $key]),
            'active' => match ($key) {
                'bundles' => $request->routeIs('app.bundles.*'),
                'progressive-gifts' => $request->routeIs('app.gifts.*'),
                default => $feature === $key,
            },
        ];
        $order = ['bundles', 'progressive-gifts', 'cart-upsells'];
        $new = Registry::newMenu();
        $checkout = ['checkout', 'post-purchase', 'thank-you', 'customer-accounts'];

        return [
            ['heading' => null, 'items' => array_values(array_filter([
                ['label' => $new ? 'Offers overview' : 'CRO overview', 'icon' => 'grid', 'tone' => 'default', 'href' => self::path('app.cro.overview'), 'active' => $request->routeIs('app.cro.overview')],
                // The new menu's picker by shopper step.
                $new ? ['label' => 'Create an offer', 'icon' => 'plus', 'tone' => 'default', 'href' => self::path('app.cro.create'), 'active' => $request->routeIs('app.cro.create')] : null,
            ]))],
            ['heading' => 'Order value', 'items' => array_map($link, array_values(array_filter($order, fn ($k) => isset($features[$k]))))],
            ['heading' => 'Conversion', 'items' => array_map($link, array_values(array_filter(array_keys($features), fn ($k) => ! in_array($k, array_merge($order, $checkout), true))))],
            ['heading' => $new ? 'Checkout and after' : 'Checkout', 'items' => array_map($link, array_values(array_filter($checkout, fn ($k) => isset($features[$k]))))],
            ['heading' => 'Manage', 'items' => [
                ['label' => $new ? 'All offers' : 'All widgets', 'icon' => 'list', 'tone' => 'default', 'href' => self::path('app.cro.experiences.index'), 'active' => $request->routeIs('app.cro.experiences.*') && ! $experienceFeature],
                ['label' => 'Templates', 'icon' => 'palette', 'tone' => 'default', 'href' => self::path('app.cro.templates'), 'active' => $request->routeIs('app.cro.templates')],
            ]],
        ];
    }

    /** The plan's sales-limit notice when the store is close, over or paused. */
    /**
     * The usage warning: the meter closest to (or at) its limit, once it passes the warning share.
     */
    private static function limit(Store $store): ?array
    {
        $warnAt = (float) config('shopify.billing.warn_at', 0.8);
        $meter = collect(app(\App\Services\Usage::class)->summary($store))
            ->filter(fn ($m) => $m['limit'] !== null && $m['limit'] > 0 && $m['used'] / $m['limit'] >= $warnAt)
            ->sortByDesc(fn ($m) => $m['used'] / $m['limit'])->first();
        if ($meter === null) {
            return null;
        }
        $full = $meter['used'] >= $meter['limit'];
        $plan = config('shopify.billing.plans.'.$store->effectivePlan().'.name');
        $label = mb_strtolower($meter['label']);

        return [
            // At the limit is normal (nothing breaks), so it's a notice, not an error.
            'state' => 'near',
            'meter' => $meter['meter'],
            'title' => $full ? "You've reached your plan's limit for {$label}." : "You're close to your plan's limit for {$label}.",
            'text' => "{$plan} includes {$meter['limit']}; you're using {$meter['used']}. ".($full ? 'Everything live keeps working; upgrade to add more.' : 'Upgrade any time for more.'),
            'action' => 'See plans',
        ];
    }

    /**
     * Named app routes for the client's route() helper: name => URI with {params}.
     *
     * @return array<string, string>
     */
    public static function routes(): array
    {
        return collect(app('router')->getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => str_starts_with($name, 'app.'))
            ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
            ->all();
    }

    /** A route as an app-relative path (the client adds the shop). */
    public static function path(string $name, array $params = []): string
    {
        return route($name, $params, false);
    }
}
