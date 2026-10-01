<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Store;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plans are limited by the store's total sales over the last 30 days. This counts those sales
 * from Shopify's orders (test and cancelled orders excluded, refunds netted), in USD, and moves
 * the store through: fine → near the limit → over (grace period) → offers paused. Upgrading or
 * dropping back under the limit clears it. Nothing is ever deleted.
 */
class SalesMeter
{
    private const PAGE = 250;

    private const MAX_PAGES = 40;

    public function __construct(private readonly AdminApi $api) {}

    /**
     * Recounts the store's sales from Shopify and applies the plan limit.
     */
    public function refresh(Store $store): void
    {
        if (! $store->isInstalled() || ! $store->hasScope('read_orders')) {
            return;
        }

        $since = now()->subDays(30)->toDateString();
        // Past the largest finite limit the exact figure no longer matters, so big stores stop early.
        $ceiling = (float) collect(config('shopify.billing.plans'))->pluck('sales_limit')->filter()->max() * 1.5;
        $total = 0.0;
        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $data = $this->api->graphql($store, <<<'GQL'
                query ($query: String!, $first: Int!, $after: String) {
                  orders(first: $first, after: $after, query: $query) {
                    nodes { currentTotalPriceSet { shopMoney { amount currencyCode } } }
                    pageInfo { hasNextPage endCursor }
                  }
                }
                GQL, ['query' => "created_at:>={$since} AND test:false AND -status:cancelled", 'first' => self::PAGE, 'after' => $cursor])['orders'] ?? [];

            foreach ($data['nodes'] ?? [] as $order) {
                $money = $order['currentTotalPriceSet']['shopMoney'] ?? [];
                $total += $this->toUsd((float) ($money['amount'] ?? 0), (string) ($money['currencyCode'] ?? $store->currency));
            }

            $cursor = $data['pageInfo']['endCursor'] ?? null;
            if (empty($data['pageInfo']['hasNextPage']) || $cursor === null || ($ceiling > 0 && $total >= $ceiling)) {
                break;
            }
        }

        $store->forceFill(['sales_30d_usd' => round($total, 2), 'sales_checked_at' => now()])->save();
        $this->evaluate($store);
    }

    /**
     * Recounts when the last count is older than $minutes. Never throws: a store we can't count
     * (no order access yet) simply isn't limited.
     */
    public function refreshIfStale(Store $store, int $minutes = 360): void
    {
        if ($store->sales_checked_at?->gt(now()->subMinutes($minutes)) || ! Cache::add("sales-meter:{$store->id}", true, 120)) {
            $this->evaluate($store);

            return;
        }

        try {
            $this->refresh($store);
        } catch (Throwable $e) {
            Log::info('Sales meter: counting store sales failed', ['store' => $store->shop_domain, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Applies the plan's limit to the last count: starts or clears the grace period, and pauses
     * or resumes the store's offers.
     */
    public function evaluate(Store $store): void
    {
        $limit = $store->salesLimit();
        $over = $limit !== null && $store->sales_30d_usd !== null && (float) $store->sales_30d_usd > $limit;
        $wasSuspended = $store->offersSuspended();

        if (! $over) {
            if ($store->over_limit_since || $wasSuspended) {
                $store->forceFill(['over_limit_since' => null, 'offers_suspended_at' => null])->save();
                if ($wasSuspended) {
                    AuditLog::record('billing.offers_resumed', $store);
                    $this->republish($store);
                }
            }

            return;
        }

        if ($store->over_limit_since === null) {
            $store->forceFill(['over_limit_since' => now()])->save();
            AuditLog::record('billing.over_sales_limit', $store, ['sales' => (float) $store->sales_30d_usd, 'limit' => $limit]);
        }

        if (! $wasSuspended && $store->over_limit_since->lte(now()->subDays((int) config('shopify.billing.grace_days')))) {
            $store->forceFill(['offers_suspended_at' => now()])->save();
            AuditLog::record('billing.offers_paused', $store, ['sales' => (float) $store->sales_30d_usd, 'limit' => $limit]);
            $this->republish($store);
        }
    }

    /**
     * What the app shows: sales against the limit and where the store stands.
     *
     * @return array{state: string, sales: ?float, limit: ?float, percent: ?int, deadline: ?\Illuminate\Support\Carbon, next: ?array}
     */
    public function status(Store $store): array
    {
        $limit = $store->salesLimit();
        $sales = $store->sales_30d_usd === null ? null : (float) $store->sales_30d_usd;
        $percent = $limit && $sales !== null ? (int) min(999, round($sales / $limit * 100)) : null;

        $state = match (true) {
            $store->offersSuspended() => 'paused',
            $store->over_limit_since !== null => 'over',
            $percent !== null && $percent >= config('shopify.billing.warn_at') * 100 => 'near',
            default => 'ok',
        };

        // The cheapest plan that fits the store's sales.
        $next = null;
        foreach (config('shopify.billing.plans') as $key => $plan) {
            if ($key !== $store->effectivePlan() && ($plan['sales_limit'] === null || ($sales ?? 0) <= $plan['sales_limit']) && $plan['price'] > (float) config('shopify.billing.plans.'.$store->effectivePlan().'.price', 0)) {
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
        ];
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
