<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\SalesCycle;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plans are limited by the store's total sales in its current 30-day cycle (cycles run back to
 * back from the first install). Every order's total is kept in store_orders: Shopify's order
 * webhooks keep it current and a sync from the Admin API fills gaps. Test and cancelled orders
 * don't count; refunds lower the order's total.
 *
 * The store moves through: fine → near the limit → over (a short grace period to upgrade) →
 * stopped (every feature off). Once over, only moving to a higher plan that fits clears it; a
 * new cycle does not. Nothing is ever deleted.
 */
class SalesMeter
{
    private const PAGE = 250;

    private const MAX_PAGES = 400;

    public function __construct(private readonly AdminApi $api) {}

    /**
     * Stores or updates one order from a Shopify order webhook (orders/create, orders/updated).
     */
    public function record(Store $store, array $order): void
    {
        if (empty($order['id'])) {
            return;
        }

        $this->upsert(
            $store,
            (string) $order['id'],
            (float) ($order['current_total_price'] ?? $order['total_price'] ?? 0),
            (string) ($order['currency'] ?? $store->currency),
            (bool) ($order['test'] ?? false),
            ! empty($order['cancelled_at']),
            isset($order['created_at']) ? Carbon::parse($order['created_at']) : now(),
        );
    }

    /**
     * After an order changed: recount the cycle and apply the plan limit. No Shopify calls.
     */
    public function recount(Store $store): void
    {
        $this->rollover($store);
        $start = $store->cycle_started_at;

        $total = $this->counted($store)->where('ordered_at', '>=', $start)->sum('amount_usd');

        $store->forceFill(['cycle_sales_usd' => round((float) $total, 2)])->save();
        $this->evaluate($store);
    }

    /**
     * Brings this cycle's orders up to date from Shopify, then recounts. The first run of a cycle
     * reads every order since the cycle started; later runs only read orders changed since the
     * last one (which also catches refunds, cancellations and missed webhooks).
     */
    public function refresh(Store $store): void
    {
        if (! $store->isInstalled() || ! $store->hasScope('read_orders')) {
            return;
        }

        $this->rollover($store);
        $start = $store->cycle_started_at;
        $incremental = $store->sales_checked_at !== null && $store->sales_checked_at->gte($start);
        $stamp = fn (Carbon $at) => "'".$at->copy()->utc()->format('Y-m-d\TH:i:s\Z')."'";
        $query = 'created_at:>='.$stamp($start)
            .($incremental ? ' AND updated_at:>='.$stamp($store->sales_checked_at->copy()->subMinutes(15)) : '');
        $startedAt = now();
        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $data = $this->api->graphql($store, <<<'GQL'
                query ($query: String!, $first: Int!, $after: String) {
                  orders(first: $first, after: $after, query: $query) {
                    nodes { id createdAt test cancelledAt currentTotalPriceSet { shopMoney { amount currencyCode } } }
                    pageInfo { hasNextPage endCursor }
                  }
                }
                GQL, ['query' => $query, 'first' => self::PAGE, 'after' => $cursor])['orders'] ?? [];

            foreach ($data['nodes'] ?? [] as $order) {
                $money = $order['currentTotalPriceSet']['shopMoney'] ?? [];
                $this->upsert(
                    $store,
                    (string) $order['id'],
                    (float) ($money['amount'] ?? 0),
                    (string) ($money['currencyCode'] ?? $store->currency),
                    (bool) ($order['test'] ?? false),
                    ! empty($order['cancelledAt']),
                    Carbon::parse($order['createdAt']),
                );
            }

            $cursor = $data['pageInfo']['endCursor'] ?? null;
            if (empty($data['pageInfo']['hasNextPage']) || $cursor === null) {
                break;
            }
        }

        $store->forceFill(['sales_checked_at' => $startedAt])->save();
        $this->recount($store);
    }

    /**
     * Syncs from Shopify when the last sync is older than $minutes; otherwise just re-applies
     * the limit (the grace period is time based). Never throws: a store we can't read orders for
     * simply isn't limited.
     */
    public function refreshIfStale(Store $store, int $minutes = 360): void
    {
        try {
            if ($store->sales_checked_at?->gt(now()->subMinutes($minutes)) || ! Cache::add("sales-meter:{$store->id}", true, 120)) {
                $this->recount($store);

                return;
            }
            $this->refresh($store);
        } catch (Throwable $e) {
            Log::info('Sales meter: counting store sales failed', ['store' => $store->shop_domain, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Applies the plan's limit to the current count: starts the grace period, stops or resumes
     * the store's features.
     */
    public function evaluate(Store $store): void
    {
        $limit = $store->salesLimit();
        $plan = $store->effectivePlan();
        $sales = $store->cycle_sales_usd === null ? null : (float) $store->cycle_sales_usd;
        $over = $limit !== null && $sales !== null && $sales > $limit;
        $wasSuspended = $store->offersSuspended();

        if (! $over && $store->over_limit_since !== null) {
            // Back under the limit only counts when it wasn't a cycle reset: a refund or
            // cancellation in the same cycle, or a move to a higher plan (or an unlimited one).
            $sameCycle = $store->cycle_started_at !== null && $store->over_limit_since->gte($store->cycle_started_at);
            $upgraded = $limit === null || $this->price($plan) > $this->price($store->over_limit_plan);

            if ($sameCycle || $upgraded) {
                $store->forceFill(['over_limit_since' => null, 'over_limit_plan' => null, 'offers_suspended_at' => null])->save();
                if ($wasSuspended) {
                    AuditLog::record('billing.features_resumed', $store, ['plan' => $plan]);
                    $this->republish($store);
                }

                return;
            }
        }

        if ($over && $store->over_limit_since === null) {
            $store->forceFill(['over_limit_since' => now(), 'over_limit_plan' => $plan])->save();
            AuditLog::record('billing.over_sales_limit', $store, ['sales' => $sales, 'limit' => $limit, 'plan' => $plan]);
        }

        if ($store->over_limit_since !== null && ! $wasSuspended
            && $store->over_limit_since->lte(now()->subDays((int) config('shopify.billing.grace_days')))) {
            $store->forceFill(['offers_suspended_at' => now()])->save();
            AuditLog::record('billing.features_stopped', $store, ['sales' => $sales, 'limit' => $limit, 'plan' => $plan]);
            $this->republish($store);
        }
    }

    /**
     * What the app shows: this cycle's sales against the limit and where the store stands.
     *
     * @return array{state: string, sales: ?float, limit: ?float, percent: ?int, deadline: ?Carbon, next: ?array, cycle_start: Carbon, cycle_end: Carbon, orders: int, test_orders: ?array}
     */
    public function status(Store $store): array
    {
        $limit = $store->salesLimit();
        $sales = $store->cycle_sales_usd === null ? null : (float) $store->cycle_sales_usd;
        $percent = $limit && $sales !== null ? (int) min(999, round($sales / $limit * 100)) : null;
        $start = $store->cycle_started_at ?? $store->cycleStart();

        $state = match (true) {
            $store->offersSuspended() => 'paused',
            $store->over_limit_since !== null => 'over',
            $percent !== null && $percent >= config('shopify.billing.warn_at') * 100 => 'near',
            default => 'ok',
        };

        // The cheapest higher plan that fits this cycle's sales.
        $next = null;
        foreach (config('shopify.billing.plans') as $key => $plan) {
            if ($plan['price'] > $this->price($store->effectivePlan()) && ($plan['sales_limit'] === null || ($sales ?? 0) <= $plan['sales_limit'])) {
                $next = ['key' => $key] + $plan;
                break;
            }
        }

        return [
            'state' => $state,
            'sales' => $sales,
            'limit' => $limit,
            'percent' => $percent,
            'deadline' => $store->over_limit_since?->copy()->addDays((int) config('shopify.billing.grace_days')),
            'next' => $next,
            'cycle_start' => $start->copy(),
            'cycle_end' => $start->copy()->addDays(Store::CYCLE_DAYS),
            'orders' => $this->counted($store)->where('ordered_at', '>=', $start)->count(),
            'test_orders' => $store->countsTestOrders() ? null : $this->testOrders($store, $start),
        ];
    }

    /**
     * Test orders this cycle that were left out of the count, so the app can say why.
     *
     * @return array{count: int, usd: float}|null
     */
    private function testOrders(Store $store, Carbon $start): ?array
    {
        $orders = StoreOrder::where('store_id', $store->id)->where('ordered_at', '>=', $start)->where('test', true)->where('cancelled', false);
        $count = $orders->count();

        return $count ? ['count' => $count, 'usd' => round((float) $orders->sum('amount_usd'), 2)] : null;
    }

    /** The orders that count toward the limit: not cancelled, and not test orders. */
    private function counted(Store $store)
    {
        return StoreOrder::where('store_id', $store->id)->where('cancelled', false)
            ->when(! $store->countsTestOrders(), fn ($q) => $q->where('test', false));
    }

    public function toUsd(float $amount, string $currency): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'USD' || $currency === '') {
            return $amount;
        }

        return $amount * ($this->rates()[$currency] ?? 1.0);
    }

    /**
     * Moves the store into the cycle that contains now, recording the one that just ended.
     */
    private function rollover(Store $store): void
    {
        if ($store->cycle_anchor_at === null) {
            $store->cycle_anchor_at = $store->installed_at ?? $store->created_at ?? now();
        }
        $current = $store->cycleStart();

        if ($store->cycle_started_at !== null && $store->cycle_started_at->lt($current)) {
            $from = $store->cycle_started_at;
            $to = $from->copy()->addDays(Store::CYCLE_DAYS);
            $orders = $this->counted($store)->where('ordered_at', '>=', $from)->where('ordered_at', '<', $to);
            $limit = $store->salesLimit();
            $sales = round((float) (clone $orders)->sum('amount_usd'), 2);

            SalesCycle::updateOrCreate(['store_id' => $store->id, 'starts_at' => $from], [
                'ends_at' => $to,
                'sales_usd' => $sales,
                'orders_count' => $orders->count(),
                'plan' => $store->effectivePlan(),
                'sales_limit' => $limit,
                'over_limit' => $limit !== null && $sales > $limit,
            ]);
        }

        if ($store->cycle_started_at === null || ! $store->cycle_started_at->equalTo($current) || $store->isDirty('cycle_anchor_at')) {
            $store->forceFill(['cycle_started_at' => $current])->save();
        }
    }

    private function upsert(Store $store, string $orderId, float $amount, string $currency, bool $test, bool $cancelled, Carbon $orderedAt): void
    {
        if (! preg_match('/(\d+)$/', $orderId, $m)) {
            return;
        }
        $currency = strtoupper($currency) ?: null;

        StoreOrder::updateOrCreate(['store_id' => $store->id, 'shopify_order_id' => $m[1]], [
            'amount' => round($amount, 2),
            'currency' => $currency,
            'amount_usd' => round($this->toUsd($amount, (string) $currency), 2),
            'test' => $test,
            'cancelled' => $cancelled,
            'ordered_at' => $orderedAt,
            'synced_at' => now(),
        ]);
    }

    private function price(?string $plan): float
    {
        return (float) config("shopify.billing.plans.{$plan}.price", 0);
    }

    /**
     * USD value of one unit of each currency: live reference rates, refreshed daily, with the
     * configured table as the fallback.
     *
     * @return array<string, float>
     */
    private function rates(): array
    {
        return Cache::remember('billing.usd_rates', 86400, function () {
            $rates = config('shopify.billing.usd_rates', []);
            try {
                $live = Http::timeout(4)->get('https://api.frankfurter.app/latest', ['from' => 'USD'])->json('rates');
                foreach ((array) $live as $code => $perUsd) {
                    if (is_numeric($perUsd) && $perUsd > 0) {
                        $rates[strtoupper((string) $code)] = 1 / (float) $perUsd;
                    }
                }
            } catch (Throwable) {
                // Offline or blocked: the configured table is close enough.
            }

            return $rates;
        });
    }

    private function republish(Store $store): void
    {
        try {
            app(StorefrontPublisher::class)->sync($store);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
