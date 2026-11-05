<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitSetting;
use App\Models\Protect\ProtectAlert;
use App\Models\Store;
use App\Services\Profit\ProfitSync;
use App\Services\Protect\ActionCenter;
use App\Services\Protect\Alerts;
use App\Services\Protect\Health;
use App\Services\Protect\Protect;
use App\Services\Protect\UrlMonitor;
use App\Support\Features;
use App\Support\Plans;
use App\Support\Spa\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Protect (phase 4): money-leak alerts (G7), inventory and days of stock (G8), product data (G9),
 * URL monitor (G10), Health Score (G12) and Action Center (G13).
 */
class ProtectController extends Controller
{
    public function __construct(private readonly Protect $protect) {}

    public function alerts(Store $store): Page
    {
        // Suggestions (severity "idea") are only in the Action Center.
        $alerts = ProtectAlert::where('store_id', $store->id)->where('severity', '!=', 'idea')->get();
        $active = app(Alerts::class)->activeRules($store);
        $off = (array) (Protect::settings($store)['rules'] ?? []);

        return $this->page($store, 'protect/alerts', [
            'open' => $alerts->where('status', 'open')->sortBy([['severity', 'desc'], ['cost', 'desc']])->values()->map(fn ($a) => $this->row($a))->all(),
            'counts' => ['snoozed' => $alerts->where('status', 'snoozed')->count(), 'muted' => $alerts->where('status', 'muted')->count(),
                'closed' => $alerts->where('status', 'closed')->where('closed_at', '>=', now()->startOfMonth())->count()],
            'snoozed' => $alerts->whereIn('status', ['snoozed', 'muted'])->values()->map(fn ($a) => $this->row($a))->all(),
            'rules' => collect(Alerts::RULES)->map(fn ($r, $key) => ['key' => $key, 'name' => $r[0], 'when' => $r[1], 'on' => $off[$key] ?? true,
                'locked' => $store->planIncludes($r[2]) ? null : (Plans::firstWith()[$r[2]] ?? null)])->values()->all(),
            'alertsCheckedAt' => $alerts->max('last_seen_at'),
        ], 'protect');
    }

    private function row(ProtectAlert $a): array
    {
        return ['id' => $a->id, 'rule' => $a->rule, 'severity' => $a->severity, 'title' => $a->title, 'what' => $a->what, 'cost_text' => $a->cost_text, 'fix' => $a->fix,
            'cta' => $a->cta, 'cta_url' => $a->cta_url, 'status' => $a->status, 'opened_at' => $a->opened_at, 'snoozed_until' => $a->snoozed_until];
    }

    /** Snooze 7 days, mute, mark done (closes until it's found again) or bring back. */
    public function alertAction(Request $request, Store $store, int $alert): RedirectResponse
    {
        $this->gate($store, 'protect');
        $a = ProtectAlert::where('store_id', $store->id)->findOrFail($alert);
        match ($request->validate(['action' => ['required', 'in:snooze,mute,done,restore']])['action']) {
            'snooze' => $a->forceFill(['status' => 'snoozed', 'snoozed_until' => now()->addDays(7)])->save(),
            'mute' => $a->forceFill(['status' => 'muted'])->save(),
            'done' => $a->forceFill(['status' => 'closed', 'closed_at' => now()])->save(),
            'restore' => $a->forceFill(['status' => 'open', 'snoozed_until' => null])->save(),
        };

        return redirect()->to(app_route($request->input('from') === 'actions' ? 'app.protect.actions' : 'app.protect.alerts', ['notice' => 'saved']));
    }

    /** Action Center (G13): open issues and suggestions, sorted by money at risk. */
    public function actions(Store $store, ActionCenter $center): Page
    {
        abort_unless(Features::enabled('protect_center', $store), 404);
        $items = $center->items($store);

        return $this->page($store, 'protect/actions', [
            'items' => $items,
            'atRisk' => round(array_sum(array_map(fn ($i) => $i['kind'] === 'issue' ? (float) $i['money'] : 0, $items)), 2),
            'checkedAt' => ProtectAlert::where('store_id', $store->id)->max('last_seen_at'),
        ], 'protect');
    }

    /** Health Score (G12): the score, the 8-week trend, what raises it most and each category. */
    public function health(Store $store, Health $health): Page
    {
        abort_unless(Features::enabled('protect_center', $store), 404);
        $now = $health->compute($store);
        $history = $health->history($store, $now['score']);
        $ups = $health->ups($now);

        return $this->page($store, 'protect/health', [
            'score' => $now['score'], 'label' => Health::label($now['score']), 'change' => $history['change'], 'weeks' => $history['weeks'], 'ups' => $ups,
            'reach' => $now['score'] === null ? null : min(100, $now['score'] + array_sum(array_column($ups, 'pts'))),
            'categories' => collect($now['categories'])->map(fn ($c, $key) => ['key' => $key, 'name' => Health::CATEGORIES[$key], 'value' => $c[0], 'note' => $c[1]])->values()->all(),
        ], 'protect');
    }

    /** URL monitor (G10). Needs no profit access: only the storefront and product handles. */
    public function urls(Store $store, UrlMonitor $monitor): Page
    {
        abort_unless(Features::enabled('url_monitor', $store), 404);
        $locked = $store->planIncludes('url_monitor') ? null : Plans::unlockMessage('url_monitor', 'see it', $store);
        // First visit: find the pages worth watching and check them, so the page isn't empty.
        if (! $locked && ! \App\Models\Protect\MonitoredUrl::where('store_id', $store->id)->exists()) {
            try {
                $monitor->discover($store);
                $monitor->check($store);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $rows = $locked ? [] : $monitor->rows($store);
        $down = array_values(array_filter($rows, fn ($r) => $r['status'] === 'down'));

        return page('protect/urls', [
            'rows' => $rows, 'down' => $down, 'limit' => $monitor->limit($store), 'used' => count($rows), 'interval' => $monitor->interval($store),
            'domain' => parse_url((string) (\App\Models\Protect\MonitoredUrl::where('store_id', $store->id)->where('kind', 'home')->value('url') ?: 'https://'.$store->shop_domain), PHP_URL_HOST),
            'passwordProtected' => collect($rows)->contains('status', 'locked'), 'locked' => $locked,
            'canEdit' => (bool) request()->user()?->can('manage_settings'),
        ]);
    }

    public function urlAdd(Request $request, Store $store, UrlMonitor $monitor): RedirectResponse
    {
        $this->gate($store, 'url_monitor', 'url_monitor');
        $data = $request->validate(['url' => ['required', 'string', 'max:500']]);
        if ($error = $monitor->add($store, $data['url'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['url' => $error]);
        }

        return redirect()->to(app_route('app.protect.urls', ['notice' => 'saved']));
    }

    public function urlRemove(Store $store, UrlMonitor $monitor, int $url): RedirectResponse
    {
        $this->gate($store, 'url_monitor', 'url_monitor');
        $monitor->remove($store, \App\Models\Protect\MonitoredUrl::where('store_id', $store->id)->findOrFail($url));

        return redirect()->to(app_route('app.protect.urls', ['notice' => 'saved']));
    }

    /** "Check now": every page again, at most once a minute; also looks for new best sellers. */
    public function urlCheck(Store $store, UrlMonitor $monitor): RedirectResponse
    {
        $this->gate($store, 'url_monitor', 'url_monitor');
        if (! \Illuminate\Support\Facades\Cache::add("url-check:{$store->id}", true, 60)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['check' => 'Checked a moment ago. You can check again in a minute.']);
        }
        $monitor->discover($store);
        $monitor->check($store, $monitor->active($store));

        return redirect()->to(app_route('app.protect.urls', ['notice' => 'saved']));
    }

    /** Which rules run, and the supplier lead time and cover days for reorder suggestions. */
    public function saveSettings(Request $request, Store $store): RedirectResponse
    {
        $this->gate($store, 'protect');
        $data = $request->validate(['rules' => ['array'], 'rules.*' => ['boolean'], 'lead_days' => ['nullable', 'integer', 'min:0', 'max:365'], 'cover_days' => ['nullable', 'integer', 'min:1', 'max:365']]);
        $settings = ProfitSetting::for($store->id);
        $current = Protect::settings($store);
        $settings->forceFill(['protect' => [
            'lead_days' => (int) ($data['lead_days'] ?? $current['lead_days']), 'cover_days' => (int) ($data['cover_days'] ?? $current['cover_days']),
            'rules' => array_intersect_key(array_map('boolval', (array) ($data['rules'] ?? $current['rules'])), Alerts::RULES),
        ]])->save();
        app(Alerts::class)->check($store);

        return redirect()->to(app_route($request->input('from') === 'inventory' ? 'app.protect.inventory' : 'app.protect.alerts', ['notice' => 'saved']));
    }

    public function inventory(Request $request, Store $store): Page
    {
        $location = (string) $request->query('location', '') ?: null;
        $rows = $this->protect->inventory($store, $location);
        $locations = ProductCost::where('store_id', $store->id)->whereNotNull('locations')->get()->flatMap(fn ($v) => collect($v->locations)->map(fn ($l) => $l[0]))->unique()->all();

        return $this->page($store, 'protect/inventory', [
            'rows' => array_slice($rows, 0, 500), 'location' => $location,
            'status' => in_array($request->query('status'), ['reorder', 'sold_out', 'healthy', 'no_sales'], true) ? $request->query('status') : null, 'locations' => $locations, 'settings' => Protect::settings($store),
            'summary' => [
                'reorder' => count(array_filter($rows, fn ($r) => $r['status'] === 'reorder')),
                'sold_out' => count(array_filter($rows, fn ($r) => $r['status'] === 'sold_out')),
                'healthy' => count(array_filter($rows, fn ($r) => $r['status'] === 'healthy')),
                'no_sales' => count(array_filter($rows, fn ($r) => $r['status'] === 'no_sales')),
                'idle_value' => round(array_sum(array_map(fn ($r) => $r['status'] === 'no_sales' ? $r['stock_value'] : 0, $rows)), 2),
            ],
            'canLocations' => $store->hasScope('read_locations'),
        ], 'protect_alerts');
    }

    public function reorderList(Request $request, Store $store): StreamedResponse
    {
        $this->gate($store, 'protect_alerts');
        $rows = array_filter($this->protect->inventory($store, (string) $request->query('location', '') ?: null), fn ($r) => in_array($r['status'], ['reorder', 'sold_out'], true));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product', 'SKU', 'In stock', 'Units sold a day (30-day average)', 'Days it lasts', 'Order by', 'Quantity to order']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], $r['sku'], $r['available'], $r['rate'], $r['days'], $r['reorder_by'], $r['qty']]);
            }
            fclose($out);
        }, 'growvia-reorder-list.csv', ['Content-Type' => 'text/csv']);
    }

    public function products(Store $store): Page
    {
        $rows = $this->protect->productData($store);
        $active = $this->protect->variants($store)->pluck('product_id')->unique()->count();
        $counts = [];
        foreach ($rows as $r) {
            foreach (array_keys($r['issues']) as $k) {
                $counts[$k] = ($counts[$k] ?? 0) + 1;
            }
        }

        return $this->page($store, 'protect/products', ['rows' => array_slice($rows, 0, 500), 'active' => $active, 'counts' => $counts], 'product_data');
    }

    /**
     * "Check now": reads products, stock and costs from Shopify again and re-checks the alerts, for
     * when something was just fixed in Shopify. At most once every 5 minutes per store.
     */
    public function refresh(Request $request, Store $store, \App\Services\Profit\CostSync $costs, ProfitSync $sync): RedirectResponse
    {
        $this->gate($store, 'protect');
        $key = "protect-refresh:{$store->id}";
        if (! \Illuminate\Support\Facades\Cache::add($key, true, 300)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['refresh' => 'Checked a moment ago. You can check again in a few minutes.']);
        }
        $costs->sync($store);
        ProfitSetting::for($store->id)->forceFill(['costs_synced_at' => now()])->save();
        $sync->costsChanged($store);
        app(Alerts::class)->check($store);
        $page = in_array($request->input('from'), ['alerts', 'inventory', 'products'], true) ? $request->input('from') : 'alerts';

        return redirect()->to(app_route("app.protect.{$page}", ['notice' => 'saved']));
    }

    /** Flag, Shopify access, then plan (a preview naming the plan that unlocks it). */
    private function page(Store $store, string $component, array $props, string $feature): Page
    {
        abort_unless(Features::enabled('protect', $store), 404);
        $missing = ProfitSync::missingAccess($store);

        $settings = ProfitSetting::for($store->id);

        return page($component, $props + [
            // Product details (stock, images, SKUs) come with the first Protect check.
            'pending' => ! ProductCost::where('store_id', $store->id)->whereNotNull('seen_at')->exists(),
            'checkedAt' => $settings->costs_synced_at,
            'access' => $missing ? ['missing' => $missing] : null,
            'syncing' => ! $missing && ! ProfitSetting::for($store->id)->backfilled_at,
            'locked' => $store->planIncludes($feature) ? null : Plans::unlockMessage($feature, 'see it', $store),
            'currency' => $store->currency ?: 'USD',
        ]);
    }

    private function gate(Store $store, string $feature, string $flag = 'protect'): void
    {
        abort_unless(Features::enabled($flag, $store), 404);
        abort_unless($store->planIncludes($feature), 403, Plans::unlockMessage($feature, 'use it', $store));
    }
}
