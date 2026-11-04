<?php

namespace App\Console\Commands;

use App\Services\Lifecycle\LifecycleEmails;
use Illuminate\Console\Command;

/** Merchant lifecycle emails (G24): welcome, setup tips, upgrade notes and monthly results. */
class LifecycleEmailsCommand extends Command
{
    protected $signature = 'orderorbit:lifecycle-emails';

    protected $description = 'Send the lifecycle emails that are due to store owners';

    public function handle(LifecycleEmails $emails): int
    {
        $this->info('Sent '.$emails->run().' lifecycle emails.');

        return self::SUCCESS;
    }
}
