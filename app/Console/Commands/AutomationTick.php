<?php

namespace App\Console\Commands;

use App\Automation\Engine;
use Illuminate\Console\Command;

/**
 * Resumes workflow runs whose wait or retry delay is over. Run every minute by the worker
 * service (php artisan schedule:work).
 */
class AutomationTick extends Command
{
    protected $signature = 'orderorbit:automation-tick';

    protected $description = 'Resume workflow runs that are due';

    public function handle(Engine $engine): int
    {
        $resumed = $engine->tick();
        if ($resumed) {
            $this->info("Resumed {$resumed} workflow runs.");
        }

        return self::SUCCESS;
    }
}
