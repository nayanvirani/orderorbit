<?php

namespace App\Services\Profit;

use App\Models\Profit\ProfitOrder;
use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Support\Features;
use Throwable;

/**
 * Runs Profit for a store: the first backfill (costs, then orders as far back as the plan's history
 * allows), costs refreshed every few hours, order webhooks as they come, and today's ad spend and
 * fixed costs added each day.
 */
class ProfitSync
{
    /** Shopify access Profit needs on top of today's. read_all_orders opens history past 60 days. */
    public const SCOPES = ['read_inventory', 'read_all_orders'];

    public function __construct(private readonly OrderSync $orders, private readonly CostSync $costs, private readonly ProfitEngine $engine) {}

    /** Profit runs for this store: the flag is on and the plan has Profit or its 30-day snapshot. */
    public static function enabled(Store $store): bool
    {
        return Features::enabled('profit', $store) && ($store->planIncludes('profit') || $store->planIncludes('profit_snapshot'));
    }

    /** Access the store hasn't approved yet (shown on the access approval screen). */
    public static function missingAccess(Store $store): array
    {
        $granted = array_filter(explode(',', (string) $store->scopes));

        return array_values(array_filter(self::SCOPES, fn ($s) => ! in_array($s, $granted, true) && ! in_array(str_replace('read_', 'write_', $s), $granted, true)));
    }

    /** Days of history this plan keeps (section 8: 30 days on Free up to 36 months). */
    public static function historyDays(Store $store): int
    {
        return (int) ($store->planLimit('history_days') ?? 1095) ?: 30;
    }

    /** One pass for one store; returns what was done (for the command's output). */
    public function run(Store $store): string
    {
        if (! self::enabled($store) || ! $store->isInstalled() || self::missingAccess($store)) {
            return 'skipped';
        }
        $settings = ProfitSetting::for($store->id);
        try {
            if (! $settings->backfilled_at) {
                $variants = $this->costs->sync($store);
                $orders = $this->orders->backfill($store, now()->subDays(self::historyDays($store)));
                $this->engine->recalculateAll($store);
                $settings->forceFill(['backfilled_at' => now(), 'costs_synced_at' => now(), 'synced_at' => now()])->save();

                return "backfilled {$orders} orders, {$variants} variants";
            }
            if (! $settings->costs_synced_at || $settings->costs_synced_at->lt(now()->subHours(6))) {
                $this->costs->sync($store);
                $settings->forceFill(['costs_synced_at' => now()])->save();
                $this->engine->recalculateAll($store);

                return 'costs refreshed';
            }
            // Today's (and yesterday's) ad spend and fixed costs, even without new orders.
            $today = now($store->timezone ?: 'UTC');
            $this->engine->recalculate($store, $today->copy()->subDay()->toDateString(), $today->toDateString());
            $settings->forceFill(['synced_at' => now()])->save();

            return 'up to date';
        } catch (Throwable $e) {
            report($e);

            return 'failed: '.mb_substr($e->getMessage(), 0, 120);
        }
    }

    /** An order webhook: save it and recalculate its day (and the day it was on before). */
    public function orderWebhook(Store $store, array $payload): void
    {
        if (! self::enabled($store) || ! ProfitSetting::where('store_id', $store->id)->whereNotNull('backfilled_at')->exists()) {
            return;
        }
        $order = $this->orders->fromWebhook($store, $payload);
        if (! $order) {
            return;
        }
        $days = array_filter([substr((string) $order->day, 0, 10), $order->previousDay ?? null]);
        $this->engine->recalculate($store, min($days), max($days));
    }

    /** After costs or rules change: everything again, so history matches the new numbers. */
    public function costsChanged(Store $store): void
    {
        $this->engine->recalculateAll($store);
    }

    public static function hasOrders(Store $store): bool
    {
        return ProfitOrder::where('store_id', $store->id)->exists();
    }
}
