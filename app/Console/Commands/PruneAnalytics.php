<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
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

        return self::SUCCESS;
    }
}
