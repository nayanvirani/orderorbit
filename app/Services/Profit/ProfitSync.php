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
        // After a failure Shopify isn't asked again every 5 minutes: the store rests 1, 2, 4, then 6 hours.
        if (($until = \Illuminate\Support\Facades\Cache::get("profit-sync-pause:{$store->id}")) && now()->lt($until)) {
            return 'paused after an error until '.\Illuminate\Support\Carbon::parse($until)->format('H:i').' UTC';
        }
        $settings = ProfitSetting::for($store->id);
        $this->ensureWebhooks($store);
        try {
            if (! $settings->backfilled_at) {
                $variants = $this->costs->sync($store);
                $orders = $this->orders->backfill($store, now()->subDays(self::historyDays($store)));
                $this->engine->recalculateAll($store);
                $settings->forceFill(['backfilled_at' => now(), 'costs_synced_at' => now(), 'synced_at' => now()])->save();
                \Illuminate\Support\Facades\Cache::forget("profit-sync-failures:{$store->id}");

                return "backfilled {$orders} orders, {$variants} variants";
            }
            if (! $settings->costs_synced_at || $settings->costs_synced_at->lt(now()->subHours(6))) {
                $this->costs->sync($store);
                $settings->forceFill(['costs_synced_at' => now()])->save();
                \Illuminate\Support\Facades\Cache::forget("profit-sync-failures:{$store->id}");
                $this->engine->recalculateAll($store);

                return 'costs refreshed';
            }
            // Products changed through webhooks since the last run: recalculate profit and alerts once.
            if (\Illuminate\Support\Facades\Cache::pull("profit-dirty:{$store->id}")) {
                $this->engine->recalculateAll($store);
                if (\App\Support\Features::enabled('protect', $store) && $store->planIncludes('protect')) {
                    app(\App\Services\Protect\Alerts::class)->check($store);
                }

                return 'recalculated after product changes';
            }
            // A development store that went live: its test orders leave profit (and the reverse).
            // Development stores are re-checked with Shopify once a day to notice the switch.
            if (OrderSync::devStore($store) && (! $store->capabilities_checked_at || $store->capabilities_checked_at->lt(now()->subDay()))) {
                $store = app(\App\Services\Shopify\StoreSync::class)->sync($store);
            }
            if ($this->applyStoreType($store)) {
                return 'test orders updated for the store type';
            }
            // Today's (and yesterday's) ad spend and fixed costs, even without new orders.
            $today = now($store->timezone ?: 'UTC');
            $this->engine->recalculate($store, $today->copy()->subDay()->toDateString(), $today->toDateString());
            $settings->forceFill(['synced_at' => now()])->save();
            \Illuminate\Support\Facades\Cache::forget("profit-sync-failures:{$store->id}");

            return 'up to date';
        } catch (Throwable $e) {
            report($e);
            $failures = (int) \Illuminate\Support\Facades\Cache::get("profit-sync-failures:{$store->id}", 0) + 1;
            \Illuminate\Support\Facades\Cache::put("profit-sync-failures:{$store->id}", $failures, now()->addDays(2));
            $until = now()->addMinutes(min(360, 60 * 2 ** ($failures - 1)));
            \Illuminate\Support\Facades\Cache::put("profit-sync-pause:{$store->id}", $until->toIso8601String(), $until);

            return 'failed (next try '.$until->format('H:i').' UTC): '.mb_substr($e->getMessage(), 0, 120);
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

    /**
     * Test orders count only on development stores. When the store's type changes, the orders that
     * no longer match are switched and all days recalculated. Returns whether anything changed.
     */
    public function applyStoreType(Store $store): bool
    {
        $dev = OrderSync::devStore($store);
        $changed = ProfitOrder::where('store_id', $store->id)->where('test', true)->where('cancelled', false)
            ->where('excluded', $dev)->update(['excluded' => ! $dev]);
        if ($changed) {
            $this->engine->recalculateAll($store);
        }

        return $changed > 0;
    }

    /**
     * A product or inventory webhook: re-read that one product now; profit and alerts recalculate
     * on the next 5-minute run (batched, so a bulk edit doesn't recalculate hundreds of times).
     */
    public function productWebhook(Store $store, string $topic, array $payload): void
    {
        if (! self::enabled($store) || ! $store->hasScope('read_inventory') || ! \App\Models\Profit\ProfitSetting::where('store_id', $store->id)->whereNotNull('backfilled_at')->exists()) {
            return;
        }
        if ($topic === 'products/delete') {
            \App\Models\Profit\ProductCost::where('store_id', $store->id)->where('product_id', (int) ($payload['id'] ?? 0))->update(['product_status' => 'DELETED']);
        } else {
            $product = str_starts_with($topic, 'products/') ? (int) ($payload['id'] ?? 0)
                : $this->costs->productForInventoryItem($store, (int) ($payload['inventory_item_id'] ?? $payload['id'] ?? 0));
            if (! $product || ! $this->costs->syncProduct($store, $product)) {
                return;
            }
        }
        \Illuminate\Support\Facades\Cache::put("profit-dirty:{$store->id}", true, now()->addDay());
    }

    /**
     * Cost and stock webhooks for this store, once it has approved read_inventory (an optional scope
     * can't be subscribed in the app config). Asked once; Shopify ignores duplicates.
     */
    public function ensureWebhooks(Store $store): void
    {
        $key = "inventory-webhooks:v1:{$store->id}";
        if (! $store->hasScope('read_inventory') || \Illuminate\Support\Facades\Cache::get($key) || \Illuminate\Support\Facades\Cache::get("{$key}:retry")) {
            return;
        }
        try {
            foreach (['INVENTORY_ITEMS_UPDATE', 'INVENTORY_LEVELS_UPDATE'] as $topic) {
                app(\App\Services\Shopify\AdminApi::class)->graphql($store, 'mutation ($topic: WebhookSubscriptionTopic!, $sub: WebhookSubscriptionInput!) { webhookSubscriptionCreate(topic: $topic, webhookSubscription: $sub) { userErrors { message } } }',
                    ['topic' => $topic, 'sub' => ['callbackUrl' => rtrim((string) config('app.url'), '/').'/webhooks/shopify', 'format' => 'JSON']]);
            }
            \Illuminate\Support\Facades\Cache::forever($key, true);
        } catch (\Throwable $e) {
            report($e);
            // Tried again in 6 hours, not on every 5-minute run.
            \Illuminate\Support\Facades\Cache::put("{$key}:retry", true, now()->addHours(6));
        }
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
