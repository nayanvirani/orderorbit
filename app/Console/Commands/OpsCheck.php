<?php

namespace App\Console\Commands;

use App\Support\OpsMonitor;
use Illuminate\Console\Command;

/** Ops alerts (G26): run the health checks, or send the daily summary with --digest. */
class OpsCheck extends Command
{
    protected $signature = 'orderorbit:ops-check {--digest : Send the daily summary instead}';

    protected $description = 'Check for webhook, automation, email and uptime problems and email the team';

    public function handle(OpsMonitor $monitor): int
    {
        if ($this->option('digest')) {
            $this->info($monitor->digest() ? 'Daily summary sent.' : 'Daily summary not sent (no team email or provider).');

            return self::SUCCESS;
        }
        $failing = $monitor->run();
        $this->info($failing ? 'Failing: '.implode(', ', $failing) : 'All checks passed.');

        return self::SUCCESS;
    }
}
