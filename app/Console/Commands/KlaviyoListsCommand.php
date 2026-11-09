<?php

namespace App\Console\Commands;

use App\Models\Integrations\StoreIntegration;
use App\Services\Integrations\Klaviyo;
use Illuminate\Console\Command;
use Throwable;

/** Klaviyo (G31): daily sync of the chosen Growvia segments to Klaviyo lists. */
class KlaviyoListsCommand extends Command
{
    protected $signature = 'orderorbit:klaviyo-lists';

    protected $description = 'Sync chosen Growvia segments to Klaviyo lists (daily)';

    public function handle(Klaviyo $klaviyo): int
    {
        StoreIntegration::with('store')->where('provider', 'klaviyo')->get()->each(function (StoreIntegration $i) use ($klaviyo) {
            $store = $i->store;
            if (! $store || ! $store->isInstalled() || ! Klaviyo::for($store) || ! $i->setting('segments')) {
                return;
            }
            try {
                $klaviyo->syncLists($store, $i);
            } catch (Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
