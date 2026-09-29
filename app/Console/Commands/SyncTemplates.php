<?php

namespace App\Console\Commands;

use App\Services\Experiences\TemplateLibrary;
use Illuminate\Console\Command;

class SyncTemplates extends Command
{
    protected $signature = 'orderorbit:sync-templates';

    protected $description = 'Sync the CRO template library from the experience type registry';

    public function handle(TemplateLibrary $library): int
    {
        $stats = $library->sync();
        $this->info("Templates synced: {$stats['created']} new, {$stats['versioned']} new versions.");

        return self::SUCCESS;
    }
}
