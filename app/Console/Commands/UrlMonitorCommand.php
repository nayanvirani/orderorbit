<?php

namespace App\Console\Commands;

use App\Models\Protect\MonitoredUrl;
use App\Models\Store;
use App\Services\Protect\UrlMonitor;
use App\Support\Features;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * URL monitor (G10): every 5 minutes, checks the pages that are due for each store (hourly, every
 * 15 or every 5 minutes by plan). Looks for new best sellers once a week and deletes checks older
 * than 30 days once a day.
 */
class UrlMonitorCommand extends Command
{
    protected $signature = 'orderorbit:url-monitor {--store= : Only this store id}';

    protected $description = 'Check monitored storefront pages that are due';

    public function handle(UrlMonitor $monitor): int
    {
        if (Cache::add('url-monitor:prune', true, 86400)) {
            UrlMonitor::prune();
        }
        // Only stores that have pages (added on the first visit) or haven't been set up yet.
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->when($this->option('store'), fn ($q, $id) => $q->whereKey($id))
            ->lazyById(50)->each(function (Store $store) use ($monitor) {
                if (! Features::enabled('url_monitor', $store) || ! $store->planIncludes('url_monitor')) {
                    return;
                }
                try {
                    if (Cache::add("url-monitor:discover:{$store->id}", true, 7 * 86400) || ! MonitoredUrl::where('store_id', $store->id)->exists()) {
                        $monitor->discover($store);
                    }
                    $r = $monitor->check($store);
                    if ($r['checked']) {
                        $this->line("{$store->shop_domain}: {$r['checked']} checked, {$r['down']} down");
                    }
                } catch (Throwable $e) {
                    report($e);
                    $this->line("{$store->shop_domain}: failed");
                }
            });

        return self::SUCCESS;
    }
}
