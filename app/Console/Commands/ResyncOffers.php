<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\Experiences\BundleSync;
use App\Services\Experiences\OfferSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rewrites each store's checkout config (cart transform and discount) from its live bundles and
 * offers, so a new config format reaches every store after a deploy. Unchanged configs aren't written.
 */
class ResyncOffers extends Command
{
    protected $signature = 'orderorbit:resync-offers {--store= : One store id}';

    protected $description = 'Rewrite the checkout config of every installed store';

    public function handle(BundleSync $bundles, OfferSync $offers): int
    {
        $done = 0;
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')
            ->when($this->option('store'), fn ($q, $id) => $q->whereKey($id))
            ->lazyById(100)
            ->each(function (Store $store) use ($bundles, $offers, &$done) {
                try {
                    $bundles->sync($store);
                    $offers->sync($store);
                    $done++;
                } catch (Throwable $e) {
                    // One store's API error never blocks the others (or the deploy).
                    Log::warning('Checkout config resync failed', ['store' => $store->shop_domain, 'error' => $e->getMessage()]);
                }
            });
        $this->info("Resynced {$done} ".\Illuminate\Support\Str::plural('store', $done).'.');

        return self::SUCCESS;
    }
}
