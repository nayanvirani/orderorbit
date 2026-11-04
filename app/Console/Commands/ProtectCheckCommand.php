<?php

namespace App\Console\Commands;

use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Services\Profit\CostSync;
use App\Services\Profit\ProfitSync;
use App\Services\Protect\Alerts;
use App\Support\Features;
use Illuminate\Console\Command;
use Throwable;

/** Protect (phase 4): refreshes stock and product data, then checks the money-leak alerts. Hourly. */
class ProtectCheckCommand extends Command
{
    protected $signature = 'orderorbit:protect-check {--store= : Only this store id}';

    protected $description = 'Refresh stock and check money-leak alerts for every store with Protect';

    public function handle(Alerts $alerts, CostSync $costs, ProfitSync $sync): int
    {
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->when($this->option('store'), fn ($q, $id) => $q->whereKey($id))
            ->lazyById(50)->each(function (Store $store) use ($alerts, $costs, $sync) {
                $settings = ProfitSetting::where('store_id', $store->id)->first();
                if (! Features::enabled('protect', $store) || ! $store->planIncludes('protect') || ! $settings?->backfilled_at || ProfitSync::missingAccess($store)) {
                    return;
                }
                try {
                    // Stock moves faster than costs: Protect refreshes the variants every hour.
                    if (! $settings->costs_synced_at || $settings->costs_synced_at->lt(now()->subMinutes(55))) {
                        $costs->sync($store);
                        $settings->forceFill(['costs_synced_at' => now()])->save();
                        $sync->costsChanged($store);
                    }
                    $r = $alerts->check($store);
                    $this->line("{$store->shop_domain}: {$r['opened']} opened, {$r['closed']} closed, {$r['open']} open");
                } catch (Throwable $e) {
                    report($e);
                    $this->line("{$store->shop_domain}: failed");
                }
            });

        return self::SUCCESS;
    }
}
