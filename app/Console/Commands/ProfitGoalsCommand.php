<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\Profit\ProfitGoals;
use Illuminate\Console\Command;
use Throwable;

/** Profit goals (G6): warns stores whose month has fallen behind the goal (once a month each). */
class ProfitGoalsCommand extends Command
{
    protected $signature = 'orderorbit:profit-goals';

    protected $description = 'Email stores whose month is behind their profit goal';

    public function handle(ProfitGoals $goals): int
    {
        $sent = 0;
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->lazyById(50)->each(function (Store $store) use ($goals, &$sent) {
            try {
                if (ProfitGoals::available($store) && $goals->alert($store)) {
                    $sent++;
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
        $this->info("{$sent} goal warnings sent.");

        return self::SUCCESS;
    }
}
