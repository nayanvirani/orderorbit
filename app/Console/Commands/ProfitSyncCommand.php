<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\Profit\ProfitSync;
use Illuminate\Console\Command;

/** Profit (phase 3): backfills new stores, refreshes costs and keeps today's numbers current. */
class ProfitSyncCommand extends Command
{
    protected $signature = 'orderorbit:profit-sync {--store= : Only this store id}';

    protected $description = 'Backfill and refresh Profit for every store that has it';

    public function handle(ProfitSync $sync): int
    {
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->when($this->option('store'), fn ($q, $id) => $q->whereKey($id))
            ->lazyById(50)->each(function (Store $store) use ($sync) {
                $result = $sync->run($store);
                if ($result !== 'skipped') {
                    $this->line("{$store->shop_domain}: {$result}");
                }
            });

        return self::SUCCESS;
    }
}
