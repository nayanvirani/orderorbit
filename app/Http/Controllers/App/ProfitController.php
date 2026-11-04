<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Profit\AdExpense;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitOrderLine;
use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Services\Profit\ProfitReports;
use App\Services\Profit\ProfitSync;
use App\Support\Spa\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profit (phase 3): dashboard, costs, ad expenses, breakdowns and ROAS/POAS. Every page reads the
 * same daily summary (ProfitReports). Behind the profit flag; until the store approves Profit's
 * Shopify access, each page shows the approval screen with sample numbers.
 */
class ProfitController extends Controller
{
    private const RANGES = ['30' => 'Last 30 days', 'this_month' => 'This month', 'last_month' => 'Last month', '90' => 'Last 90 days'];

    public function __construct(private readonly ProfitReports $reports) {}

    public function dashboard(Request $request, Store $store): Page
    {
        [$range, $from, $to] = $this->range($request, $store);
        $t = $this->reports->totals($store, $from, $to);
        $days = $from->diffInDays($to) + 1;
        $prev = $this->reports->totals($store, $from->copy()->subDays($days), $from->copy()->subDay());
        $products = $this->reports->breakdown($store, $from, $to, 'product');

        return $this->page($store, 'profit/dashboard', [
            'range' => $range, 'ranges' => self::RANGES, 'totals' => $t, 'previous' => $prev,
            'daily' => $this->reports->daily($store, $from, $to),
            'products' => array_slice($products, 0, 8),
            'adsEntered' => AdExpense::where('store_id', $store->id)->exists(),
        ]);
    }

    public function costs(Request $request, Store $store): Page
    {
        $settings = ProfitSetting::for($store->id);
        $since = now()->subDays(30)->toDateString();
        // Sales in the last 30 days per variant, to sort missing costs by what's at risk.
        $sales = ProfitOrderLine::query()->join('profit_orders', 'profit_orders.id', '=', 'profit_order_lines.profit_order_id')
            ->where('profit_order_lines.store_id', $store->id)->where('profit_orders.excluded', false)->where('profit_orders.day', '>=', $since)
            ->selectRaw('profit_order_lines.variant_id, sum(profit_order_lines.price * profit_order_lines.quantity - profit_order_lines.discount - profit_order_lines.refunded) as sales, max(profit_order_lines.price) as price')
            ->groupBy('profit_order_lines.variant_id')->get()->keyBy('variant_id');
        $all = ProductCost::where('store_id', $store->id)->get();
        $missing = $all->filter(fn ($c) => $c->effective() === null)
            ->map(fn ($c) => ['variant_id' => (int) $c->variant_id, 'name' => $c->title, 'price' => (float) ($c->price ?? $sales[$c->variant_id]->price ?? 0), 'sales' => round((float) ($sales[$c->variant_id]->sales ?? 0), 2)])
            ->sortByDesc('sales')->values();
        $withCost = $all->filter(fn ($c) => $c->effective() !== null);
        [, $from, $to] = $this->range(new Request(['range' => '30']), $store);
        $t = $this->reports->totals($store, $from, $to);

        return $this->page($store, 'profit/costs', [
            'summary' => [
                'variants' => $all->count(), 'withCost' => $withCost->count(), 'missingSales' => $t['missing_cost_sales'],
                'missingShare' => $t['net_sales'] > 0 ? round($t['missing_cost_sales'] / $t['net_sales'] * 100, 1) : 0,
                'fromShopify' => $withCost->filter(fn ($c) => $c->cost === null)->count(), 'inGrowvia' => $withCost->filter(fn ($c) => $c->cost !== null)->count(),
            ],
            'missing' => $missing->take(200)->all(),
            'overrides' => $all->filter(fn ($c) => $c->cost !== null)->sortBy('title')->take(200)->map(fn ($c) => ['variant_id' => (int) $c->variant_id, 'name' => $c->title, 'cost' => $c->cost, 'shopify' => $c->shopify_cost, 'source' => $c->source])->values()->all(),
            'rules' => ['fees' => $settings->fees, 'shipping' => $settings->shipping, 'fixed_costs' => $settings->fixed_costs ?? [], 'default_cost_percent' => $settings->default_cost_percent],
        ], 'profit');
    }

    /** Unit costs set in Growvia: {variant_id: cost|null} (null clears it back to Shopify's). */
    public function saveCosts(Request $request, Store $store, ProfitSync $sync): RedirectResponse
    {
        $this->gate($store, 'profit');
        $data = $request->validate(['costs' => ['required', 'array', 'max:2000'], 'costs.*' => ['nullable', 'numeric', 'min:0', 'max:1000000']]);
        foreach ($data['costs'] as $variant => $cost) {
            ProductCost::where('store_id', $store->id)->where('variant_id', (int) $variant)->update(['cost' => $cost === null || $cost === '' ? null : round((float) $cost, 2), 'source' => $cost === null || $cost === '' ? null : ($request->input('source') === 'percent' ? 'percent' : 'manual')]);
        }
        $sync->costsChanged($store);

        return redirect()->to(app_route('app.profit.costs', ['notice' => 'saved']));
    }

    public function saveRules(Request $request, Store $store, ProfitSync $sync): RedirectResponse
    {
        $this->gate($store, 'profit');
        $data = $request->validate([
            'fees.percent' => ['required', 'numeric', 'min:0', 'max:20'], 'fees.fixed' => ['required', 'numeric', 'min:0', 'max:100'],
            'fees.countries' => ['array', 'max:50'], 'fees.countries.*.percent' => ['numeric', 'min:0', 'max:20'], 'fees.countries.*.fixed' => ['numeric', 'min:0', 'max:100'],
            'shipping.mode' => ['required', 'in:none,per_order,per_item,percent'], 'shipping.value' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'fixed_costs' => ['array', 'max:50'], 'fixed_costs.*.name' => ['required', 'string', 'max:80'], 'fixed_costs.*.amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'default_cost_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $countries = collect($data['fees']['countries'] ?? [])->filter(fn ($v, $k) => preg_match('/^[A-Z]{2}$/', (string) $k))->all();
        ProfitSetting::for($store->id)->update([
            'fees' => ['percent' => (float) $data['fees']['percent'], 'fixed' => (float) $data['fees']['fixed'], 'countries' => $countries],
            'shipping' => ['mode' => $data['shipping']['mode'], 'value' => (float) ($data['shipping']['value'] ?? 0)],
            'fixed_costs' => array_values($data['fixed_costs'] ?? []),
            'default_cost_percent' => $data['default_cost_percent'] ?? null,
        ]);
        $sync->costsChanged($store);

        return redirect()->to(app_route('app.profit.costs', ['notice' => 'saved']));
    }

    /** Costs from a CSV: variant id or SKU, then cost. */
    public function importCosts(Request $request, Store $store, ProfitSync $sync): RedirectResponse
    {
        $this->gate($store, 'profit');
        $request->validate(['file' => ['required', 'file', 'max:2048']]);
        $rows = array_map('str_getcsv', array_filter(preg_split('/\r\n|\r|\n/', (string) file_get_contents($request->file('file')->getRealPath()))));
        $skus = ProfitOrderLine::where('store_id', $store->id)->whereNotNull('sku')->pluck('variant_id', 'sku');
        $saved = 0;
        foreach (array_slice($rows, 1) as $row) {
            [$key, $cost] = [trim((string) ($row[0] ?? '')), trim((string) ($row[array_key_last($row)] ?? ''))];
            $variant = ctype_digit($key) ? (int) $key : (int) ($skus[$key] ?? 0);
            if ($variant && is_numeric($cost)) {
                $saved += ProductCost::where('store_id', $store->id)->where('variant_id', $variant)->update(['cost' => round((float) $cost, 2), 'source' => 'csv']);
            }
        }
        if (! $saved) {
            throw ValidationException::withMessages(['file' => 'No costs matched. Use the template: variant ID (or SKU) in the first column, cost in the last.']);
        }
        $sync->costsChanged($store);

        return redirect()->to(app_route('app.profit.costs', ['notice' => 'saved']));
    }

    public function costTemplate(Store $store): StreamedResponse
    {
        $rows = ProductCost::where('store_id', $store->id)->orderBy('title')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Variant ID', 'Product', 'Shopify cost', 'Cost']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->variant_id, $r->title, $r->shopify_cost, $r->cost]);
            }
            fclose($out);
        }, 'growvia-costs.csv', ['Content-Type' => 'text/csv']);
    }

    public function ads(Request $request, Store $store): Page
    {
        $ads = AdExpense::where('store_id', $store->id)->orderByDesc('starts_on')->get();
        [, $from, $to] = $this->range(new Request(['range' => '30']), $store);
        $byChannel = [];
        foreach ($ads as $ad) {
            $spend = 0.0;
            foreach (\Carbon\CarbonPeriod::create($from, $to) as $d) {
                $spend += app(\App\Services\Profit\ProfitEngine::class)->adSpend([$ad], $d);
            }
            $byChannel[$ad->channel] = ($byChannel[$ad->channel] ?? 0) + $spend;
        }
        arsort($byChannel);

        return $this->page($store, 'profit/ads', [
            'channels' => AdExpense::CHANNELS, 'currency' => $store->currency ?: 'USD',
            'byChannel' => collect($byChannel)->map(fn ($v, $k) => ['name' => AdExpense::CHANNELS[$k] ?? $k, 'amount' => round($v, 2)])->values()->all(),
            'entries' => $ads->map(fn ($a) => ['id' => $a->id, 'starts_on' => $a->starts_on->toDateString(), 'ends_on' => $a->ends_on?->toDateString(), 'recurring' => $a->recurring,
                'channel' => $a->channel, 'amount' => $a->amount, 'note' => $a->note, 'tags' => $a->tags ?? []])->all(),
        ], 'profit');
    }

    public function saveAd(Request $request, Store $store, ProfitSync $sync, ?int $ad = null): RedirectResponse
    {
        $this->gate($store, 'profit');
        $data = $request->validate([
            'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'recurring' => ['boolean'],
            'channel' => ['required', 'in:'.implode(',', array_keys(AdExpense::CHANNELS))], 'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'], 'tags' => ['array', 'max:50'], 'tags.*.type' => ['in:product,collection'],
            'tags.*.id' => ['string', 'regex:#^gid://shopify/(Product|Collection)/\d+$#'], 'tags.*.title' => ['nullable', 'string', 'max:255'],
        ]);
        $values = ['currency' => $store->currency, 'recurring' => (bool) ($data['recurring'] ?? false)] + array_intersect_key($data, array_flip(['starts_on', 'ends_on', 'channel', 'amount', 'note', 'tags']));
        $ad ? AdExpense::where('store_id', $store->id)->findOrFail($ad)->update($values) : AdExpense::create(['store_id' => $store->id] + $values);
        $sync->costsChanged($store);

        return redirect()->to(app_route('app.profit.ads', ['notice' => 'saved']));
    }

    public function deleteAd(Store $store, ProfitSync $sync, int $ad): RedirectResponse
    {
        $this->gate($store, 'profit');
        AdExpense::where('store_id', $store->id)->findOrFail($ad)->delete();
        $sync->costsChanged($store);

        return redirect()->to(app_route('app.profit.ads', ['notice' => 'saved']));
    }

    /** Ad spend from any platform's CSV: date (or start and end date), channel, amount. */
    public function importAds(Request $request, Store $store, ProfitSync $sync): RedirectResponse
    {
        $this->gate($store, 'profit');
        $request->validate(['file' => ['required', 'file', 'max:2048'], 'channel' => ['required', 'in:'.implode(',', array_keys(AdExpense::CHANNELS))]]);
        $rows = array_map('str_getcsv', array_filter(preg_split('/\r\n|\r|\n/', (string) file_get_contents($request->file('file')->getRealPath()))));
        $head = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0] ?? []);
        $col = fn (array $names) => collect($head)->search(fn ($h) => collect($names)->contains(fn ($n) => str_contains($h, $n)));
        $date = $col(['date', 'day', 'start']);
        $end = $col(['end']);
        $amount = $col(['amount spent', 'spend', 'cost', 'amount']);
        if ($date === false || $amount === false) {
            throw ValidationException::withMessages(['file' => 'The CSV needs a date column and a spend (or cost, amount) column.']);
        }
        $saved = 0;
        DB::transaction(function () use ($rows, $date, $end, $amount, $store, $request, &$saved) {
            foreach (array_slice($rows, 1) as $row) {
                $spend = (float) preg_replace('/[^\d.\-]/', '', (string) ($row[$amount] ?? ''));
                try {
                    $start = \Carbon\Carbon::parse((string) ($row[$date] ?? ''))->toDateString();
                    $stop = $end !== false && ! empty($row[$end]) ? \Carbon\Carbon::parse((string) $row[$end])->toDateString() : null;
                } catch (\Throwable) {
                    continue;
                }
                if ($spend > 0) {
                    AdExpense::create(['store_id' => $store->id, 'starts_on' => $start, 'ends_on' => $stop, 'channel' => $request->input('channel'), 'amount' => round($spend, 2), 'currency' => $store->currency, 'note' => 'CSV import']);
                    $saved++;
                }
            }
        });
        if (! $saved) {
            throw ValidationException::withMessages(['file' => 'No rows with a date and spend were found.']);
        }
        $sync->costsChanged($store);

        return redirect()->to(app_route('app.profit.ads', ['notice' => 'saved']));
    }

    public function breakdowns(Request $request, Store $store): Page
    {
        [$range, $from, $to] = $this->range($request, $store);
        $by = in_array($request->query('by'), ['product', 'variant', 'order', 'channel', 'country', 'day'], true) ? $request->query('by') : 'product';

        return $this->page($store, 'profit/breakdowns', [
            'range' => $range, 'ranges' => self::RANGES, 'by' => $by,
            'rows' => array_slice($this->reports->breakdown($store, $from, $to, $by), 0, 500),
            'totals' => $this->reports->totals($store, $from, $to),
        ], 'profit');
    }

    public function exportBreakdown(Request $request, Store $store): StreamedResponse
    {
        $this->gate($store, 'profit');
        [, $from, $to] = $this->range($request, $store);
        $by = in_array($request->query('by'), ['product', 'variant', 'order', 'channel', 'country', 'day'], true) ? $request->query('by') : 'product';
        $rows = $this->reports->breakdown($store, $from, $to, $by);

        return response()->streamDownload(function () use ($rows, $by) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [ucfirst($by), 'Orders', 'Units', 'Net sales', 'Product cost', 'Fees and shipping', 'Contribution', 'Ad spend', 'Profit after ads', 'Margin %']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], $r['orders'], $r['units'], $r['net_sales'], $r['cogs'], $r['fees_and_shipping'], $r['contribution'], $r['ad_spend'], $r['after_ads'], $r['margin']]);
            }
            fclose($out);
        }, "growvia-profit-by-{$by}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function roas(Request $request, Store $store): Page
    {
        [$range, $from, $to] = $this->range($request, $store);
        $t = $this->reports->totals($store, $from, $to);
        $products = collect($this->reports->breakdown($store, $from, $to, 'product'))->map(fn ($r) => $r + [
            'break_even' => $r['contribution_margin'] > 0 ? round(100 / $r['contribution_margin'], 2) : null,
            'roas' => $r['tagged_spend'] > 0 ? round($r['net_sales'] / $r['tagged_spend'], 2) : null,
            'poas' => $r['tagged_spend'] > 0 ? round($r['contribution'] / $r['tagged_spend'], 2) : null,
        ])->values()->all();

        return $this->page($store, 'profit/roas', [
            'range' => $range, 'ranges' => self::RANGES, 'totals' => $t,
            'store' => [
                'break_even' => $t['contribution_margin'] > 0 ? round(100 / $t['contribution_margin'], 2) : null,
                'roas' => $t['ad_spend'] > 0 ? round($t['net_sales'] / $t['ad_spend'], 2) : null,
                'poas' => $t['ad_spend'] > 0 ? round($t['contribution'] / $t['ad_spend'], 2) : null,
            ],
            'products' => array_slice($products, 0, 100),
        ], 'profit_roas');
    }

    /** After Shopify's approval window: read the granted access now rather than waiting for the webhook. */
    public function accessGranted(Request $request, Store $store, \App\Services\Shopify\AdminApi $api): RedirectResponse
    {
        try {
            $scopes = array_column((array) ($api->graphql($store, '{ currentAppInstallation { accessScopes { handle } } }')['currentAppInstallation']['accessScopes'] ?? []), 'handle');
            if ($scopes) {
                $store->forceFill(['scopes' => implode(',', $scopes)])->save();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Back to the page the approval was asked from (an app path only).
        $back = (string) $request->input('back', '');

        if (preg_match('#^/app(/[A-Za-z0-9/_\-]*)?$#', $back)) {
            return redirect()->to(url($back).'?'.http_build_query(array_filter(['shop' => $request->query('shop') ?? $store->shop_domain, 'host' => $request->query('host'), 'notice' => 'saved'])));
        }

        return redirect()->to(app_route('app.profit.dashboard', ['notice' => 'saved']));
    }

    /** [range key, from, to] from ?range=, within the plan's history. */
    private function range(Request $request, Store $store): array
    {
        $key = array_key_exists((string) $request->query('range'), self::RANGES) ? (string) $request->query('range') : '30';
        [$from, $to] = ProfitReports::range($store, $key);
        $oldest = now($store->timezone ?: 'UTC')->startOfDay()->subDays(ProfitSync::historyDays($store) - 1);

        return [$key, $from->lt($oldest) ? $oldest : $from, $to];
    }

    /**
     * Every Profit page: the flag, then Shopify access (approval screen), then the plan (preview
     * with a note naming the plan). Free sees the 30-day snapshot on the dashboard.
     */
    private function page(Store $store, string $component, array $props, string $feature = 'profit_snapshot'): Page
    {
        abort_unless(\App\Support\Features::enabled('profit', $store), 404);
        $missing = ProfitSync::missingAccess($store);
        $settings = ProfitSetting::for($store->id);

        return page($component, $props + [
            'access' => $missing ? ['missing' => $missing] : null,
            'syncing' => ! $missing && ! $settings->backfilled_at,
            'locked' => $store->planIncludes($feature) && ($feature !== 'profit_snapshot' || $component === 'profit/dashboard') ? null
                : \App\Support\Plans::unlockMessage($feature === 'profit_snapshot' ? 'profit' : $feature, 'see it', $store),
            'currency' => $store->currency ?: 'USD',
            'snapshot' => ! $store->planIncludes('profit'),
        ]);
    }

    private function gate(Store $store, string $feature): void
    {
        abort_unless(\App\Support\Features::enabled('profit', $store), 404);
        abort_unless($store->planIncludes($feature), 403, \App\Support\Plans::unlockMessage($feature, 'use it', $store));
    }
}
