<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\RecentPurchase;
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
        $this->info("Pruned {$deleted} analytics events older than ".self::RETENTION_DAYS.' days.');

        $purchases = RecentPurchase::where('purchased_at', '<', now()->subDays(RecentPurchase::MAX_DAYS))->delete();
        $this->info("Pruned {$purchases} Sales pop purchases older than ".RecentPurchase::MAX_DAYS.' days.');

        return self::SUCCESS;
    }
}
