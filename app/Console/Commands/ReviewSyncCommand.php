<?php

namespace App\Console\Commands;

use App\Models\Integrations\StoreIntegration;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Integrations\ReviewSync;
use Illuminate\Console\Command;
use Throwable;

/** Review apps, step 2 (G30): daily sync of published reviews, then the storefront is republished. */
class ReviewSyncCommand extends Command
{
    protected $signature = 'orderorbit:sync-reviews';

    protected $description = 'Sync published reviews from Judge.me and Yotpo (daily)';

    public function handle(ReviewSync $sync, StorefrontPublisher $publisher): int
    {
        StoreIntegration::with('store')->whereIn('provider', array_keys(ReviewSync::SOURCES))->get()->groupBy('store_id')->each(function ($rows) use ($sync, $publisher) {
            $store = $rows->first()->store;
            if (! $store || ! $store->isInstalled() || ! ReviewSync::connected($store)) {
                return;
            }
            try {
                foreach ($rows as $i) {
                    $sync->sync($store, $i);
                }
                $publisher->publish($store);
            } catch (Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
