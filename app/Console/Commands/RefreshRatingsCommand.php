<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Reviews\ProductRatings;
use Illuminate\Console\Command;
use Throwable;

/** Review stars (G30 step 1): republishes stores' offers once a day so ratings stay current. */
class RefreshRatingsCommand extends Command
{
    protected $signature = 'orderorbit:refresh-ratings {--store= : Only this store id}';

    protected $description = 'Refresh review star ratings on published offers (daily)';

    public function handle(StorefrontPublisher $publisher): int
    {
        $done = 0;
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->when($this->option('store'), fn ($q, $id) => $q->whereKey($id))
            ->lazyById(50)->each(function (Store $store) use ($publisher, &$done) {
                if (! ProductRatings::enabled($store) || ! $store->experiences()->where('status', 'published')->exists()) {
                    return;
                }
                try {
                    $publisher->publish($store);
                    $done++;
                } catch (Throwable $e) {
                    report($e);
                }
            });
        $this->info("Ratings refreshed for {$done} stores.");

        return self::SUCCESS;
    }
}
