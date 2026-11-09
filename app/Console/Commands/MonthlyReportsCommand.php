<?php

namespace App\Console\Commands;

use App\Services\Insights\MonthlyReportSender;
use Illuminate\Console\Command;

/** Monthly Business Report (G15): last month's report to each store that hasn't had it yet. */
class MonthlyReportsCommand extends Command
{
    protected $signature = 'orderorbit:monthly-reports';

    protected $description = 'Email last month\'s Monthly Business Report (once per store and month)';

    public function handle(MonthlyReportSender $sender): int
    {
        $this->info('Sent '.$sender->run().' monthly reports.');

        return self::SUCCESS;
    }
}
