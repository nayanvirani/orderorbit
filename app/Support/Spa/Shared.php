<?php

namespace App\Support\Spa;

use App\Experiences\Registry;
use App\Models\Experience;
use App\Models\Store;
use App\Services\Billing\SalesMeter;
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
        $access = $store?->hasPlanAccess() && ! $store->offersSuspended();

        return [
            'shop' => $store?->shop_domain,
            'storeName' => $store?->name,
            'currency' => $store?->currency ?? 'USD',
            'user' => $user ? ['name' => $user->first_name, 'role' => $user->role] : null,
            'can' => collect(array_keys(Permissions::MATRIX))->mapWithKeys(fn ($p) => [$p => (bool) $user?->can($p)])->all(),
            'plan' => $store?->effectivePlan(),
            'includes' => array_values((array) config('shopify.billing.plans.'.$store?->effectivePlan().'.includes', [])),
            'access' => (bool) $access,
            'goal' => (bool) $store?->goal,
            'adminUrl' => $store?->adminUrl(),
            'nav' => $access ? self::nav($request, $store) : null,
            'limit' => $access && ! $request->routeIs('app.settings.billing') ? self::limit($store) : null,
        ];
    }

    private static function nav(Request $request, Store $store): ?array
    {
        if ($request->routeIs('app.cro.*', 'app.bundles.*', 'app.gifts.*', 'app.features.*')) {
            return ['section' => 'cro', 'label' => 'CRO', 'groups' => self::croGroups($request, $store)];
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
        $checkout = ['checkout', 'post-purchase', 'thank-you', 'customer-accounts'];

        return [
            ['heading' => null, 'items' => [['label' => 'CRO overview', 'icon' => 'grid', 'tone' => 'default', 'href' => self::path('app.cro.overview'), 'active' => $request->routeIs('app.cro.overview')]]],
            ['heading' => 'Order value', 'items' => array_map($link, array_values(array_filter($order, fn ($k) => isset($features[$k]))))],
            ['heading' => 'Conversion', 'items' => array_map($link, array_values(array_filter(array_keys($features), fn ($k) => ! in_array($k, array_merge($order, $checkout), true))))],
            ['heading' => 'Checkout', 'items' => array_map($link, array_values(array_filter($checkout, fn ($k) => isset($features[$k]))))],
            ['heading' => 'Manage', 'items' => [
                ['label' => 'All offers', 'icon' => 'list', 'tone' => 'default', 'href' => self::path('app.cro.experiences.index'), 'active' => $request->routeIs('app.cro.experiences.*') && ! $experienceFeature],
                ['label' => 'Templates', 'icon' => 'palette', 'tone' => 'default', 'href' => self::path('app.cro.templates'), 'active' => $request->routeIs('app.cro.templates')],
            ]],
        ];
    }

    /** The plan's sales-limit notice when the store is close, over or paused. */
    private static function limit(Store $store): ?array
    {
        $status = app(SalesMeter::class)->status($store);
        if ($status['state'] === 'ok') {
            return null;
        }
        $money = fn ($v) => '$'.number_format((float) $v);
        $plan = config('shopify.billing.plans.'.$store->effectivePlan().'.name');

        return ['state' => $status['state']] + match ($status['state']) {
            'paused' => ['title' => 'All features are stopped.', 'text' => "Your store passed the {$plan} plan's {$money($status['limit'])} sales limit. Upgrade and everything goes live again straight away. Nothing was deleted.", 'action' => 'Upgrade plan'],
            'over' => ['title' => 'Upgrade required: you\'ve passed your plan\'s sales limit.', 'text' => "{$plan} covers up to {$money($status['limit'])} per cycle. Upgrade by {$status['deadline']->toFormattedDateString()} or every feature stops on your store.", 'action' => 'Upgrade plan'],
            default => ['title' => 'You\'re close to your plan\'s sales limit.', 'text' => "Your store sold {$money($status['sales'])} of {$money($status['limit'])} this cycle ({$status['percent']}%). Past the limit you'll have ".config('shopify.billing.grace_days').' days to upgrade.', 'action' => 'See plans'],
        };
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
