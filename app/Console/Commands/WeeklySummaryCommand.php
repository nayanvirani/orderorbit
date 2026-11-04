<?php

namespace App\Console\Commands;

use App\Services\Profit\WeeklySummary;
use Illuminate\Console\Command;

/** Weekly summary email (G16): last week's sales and profit to the people who can see profit. */
class WeeklySummaryCommand extends Command
{
    protected $signature = 'orderorbit:weekly-summary';

    protected $description = 'Send the Monday summary email';

    public function handle(WeeklySummary $summary): int
    {
        $this->info('Sent '.$summary->run().' weekly summaries.');

        return self::SUCCESS;
    }
}
