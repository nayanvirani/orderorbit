<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\ContactSubmission;
use App\Models\RecentPurchase;
use App\Models\Store;
use Illuminate\Console\Command;

/**
 * Retention: analytics events, Sales pop purchases, contact messages and automation data
 * (customers' names and emails) older than their retention window are deleted. Daily.
 */
class PruneAnalytics extends Command
{
    public const RETENTION_DAYS = 400;

    /** Customers' names and emails in automation data. */
    public const PERSONAL_DATA_MONTHS = 12;

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

        $purchases = RecentPurchase::where('purchased_at', '<', now()->subDays(RecentPurchase::MAX_DAYS))->delete();
        $this->info("Pruned {$purchases} Sales pop purchases older than ".RecentPurchase::MAX_DAYS.' days.');

        // Website contact-form messages: kept 24 months (Privacy Policy, Retention).
        $messages = ContactSubmission::where('created_at', '<', now()->subMonths(24))->delete();
        $this->info("Pruned {$messages} contact-form messages older than 24 months.");

        // Automation (customer names and emails): runs, their logs, queued customer emails, done
        // inbox items and the email delivery log are kept 12 months (Privacy Policy, Retention).
        $cutoff = now()->subMonths(self::PERSONAL_DATA_MONTHS);
        $runs = \App\Models\Automation\WorkflowRun::where('created_at', '<', $cutoff)->whereNotIn('status', ['waiting', 'running'])->delete();
        $emails = \App\Models\Automation\AutomationEmail::where('created_at', '<', $cutoff)->delete();
        $inbox = \App\Models\Automation\InboxItem::where('created_at', '<', $cutoff)->whereNotNull('done_at')->delete();
        $deliveries = \App\Models\Mail\EmailDelivery::where('created_at', '<', $cutoff)->delete();
        $this->info("Pruned {$runs} workflow runs, {$emails} customer emails, {$inbox} inbox items and {$deliveries} delivery records older than ".self::PERSONAL_DATA_MONTHS.' months.');

        return self::SUCCESS;
    }
}
