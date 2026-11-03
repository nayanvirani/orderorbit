<?php

namespace App\Console\Commands;

use App\Support\Announcements;
use Illuminate\Console\Command;

/** Emails store owners when an announcement marked "email owners" starts (G41). */
class AnnouncementEmails extends Command
{
    protected $signature = 'orderorbit:announcement-emails';

    protected $description = 'Email store owners about announcements that just started';

    public function handle(): int
    {
        $this->info('Sent '.Announcements::sendDueEmails().' announcement emails.');

        return self::SUCCESS;
    }
}
