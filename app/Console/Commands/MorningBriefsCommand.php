<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\Insights\MorningBrief;
use Illuminate\Console\Command;
use Throwable;

/** AI Morning Brief (G17): today's brief for each store, once, from 6 am in its time zone. */
class MorningBriefsCommand extends Command
{
    protected $signature = 'orderorbit:morning-briefs';

    protected $description = 'Write today\'s Morning Brief for each store (once a day)';

    public function handle(MorningBrief $briefs): int
    {
        $made = 0;
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->lazyById(50)->each(function (Store $store) use ($briefs, &$made) {
            if (now($store->timezone ?: 'UTC')->hour < 6 || ! MorningBrief::available($store)) {
                return;
            }
            try {
                if ($briefs->make($store) !== null) {
                    $made++;
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
        $this->info("{$made} briefs made.");

        return self::SUCCESS;
    }
}
