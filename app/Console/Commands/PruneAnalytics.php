<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\RecentPurchase;
use App\Models\Store;
use App\Models\StoreOrder;
use Illuminate\Console\Command;

/**
 * Analytics retention: events older than the retention window are deleted.
 */
class PruneAnalytics extends Command
{
    public const RETENTION_DAYS = 400;

    protected $signature = 'orderorbit:prune-analytics';

    protected $description = 'Delete analytics events older than the retention window';

    public function handle(): int
    {
        $deleted = AnalyticsEvent::where('occurred_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
        // Stores that chose a shorter retention in Settings → Privacy.
        foreach (Store::whereNotNull('privacy')->get() as $store) {
            $months = (int) $store->privacy('retention_months');
            if ($months < 13) {
                $deleted += AnalyticsEvent::where('store_id', $store->id)->where('occurred_at', '<', now()->subMonths($months))->delete();
            }
        }
        $this->info("Pruned {$deleted} analytics events older than ".self::RETENTION_DAYS.' days.');

        // Order totals are only needed for the current and recent sales cycles.
        $orders = StoreOrder::where('ordered_at', '<', now()->subDays(StoreOrder::KEEP_CYCLES * Store::CYCLE_DAYS))->delete();
        $this->info("Pruned {$orders} order totals older than ".StoreOrder::KEEP_CYCLES.' sales cycles.');

        $purchases = RecentPurchase::where('purchased_at', '<', now()->subDays(RecentPurchase::MAX_DAYS))->delete();
        $this->info("Pruned {$purchases} Sales pop purchases older than ".RecentPurchase::MAX_DAYS.' days.');

        return self::SUCCESS;
    }
}
