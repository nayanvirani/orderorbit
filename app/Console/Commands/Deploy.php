<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Railway pre-deploy step: migrate, then sync the template library.
 */
class Deploy extends Command
{
    protected $signature = 'orderorbit:deploy';

    protected $description = 'Run migrations and sync the template library before a deploy goes live';

    public function handle(): int
    {
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        return $this->call('orderorbit:sync-templates');
    }
}
