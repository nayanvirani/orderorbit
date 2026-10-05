<?php

namespace App\Console\Commands;

use App\Models\Automation\AutomationEmail;
use App\Models\Store;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use Illuminate\Console\Command;

/**
 * Sends the emails workflows prepared, through the providers set up in the super admin.
 * Emails that can't go yet are retried with growing delays; after 3 days they expire.
 */
class SendEmails extends Command
{
    public const MAX_AGE_HOURS = 72;

    /** Minutes to wait before each retry. */
    private const BACKOFF = [1, 5, 15, 30, 60, 120, 240, 480];

    protected $signature = 'orderorbit:send-emails {--limit=200}';

    protected $description = 'Send prepared workflow emails through the configured email providers';

    public function handle(EmailSender $sender): int
    {
        $due = AutomationEmail::whereIn('status', ['queued', 'retrying', 'waiting_for_provider'])
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit((int) $this->option('limit'))->get();
        $stores = Store::whereIn('id', $due->pluck('store_id')->unique())->get()->keyBy('id');
        $counts = ['sent' => 0, 'waiting' => 0, 'failed' => 0, 'expired' => 0];

        foreach ($due as $row) {
            $store = $stores[$row->store_id] ?? null;
            if ($store === null || ! $store->isInstalled()) {
                $row->forceFill(['status' => 'failed', 'error' => 'The store uninstalled the app.'])->save();
                $counts['failed']++;

                continue;
            }
            if ($row->created_at->lt(now()->subHours(self::MAX_AGE_HOURS))) {
                $row->forceFill(['status' => 'expired', 'error' => 'Not sent within '.(self::MAX_AGE_HOURS / 24).' days, so it was dropped rather than arriving late.'])->save();
                $counts['expired']++;

                continue;
            }
            if (! $row->to_email) {
                $row->forceFill(['status' => 'failed', 'error' => 'This customer has no email address.'])->save();
                $counts['failed']++;

                continue;
            }

            $result = $sender->send(OutgoingEmail::fromText($row->to_email, $row->subject, $row->body, [
                'fromName' => $store->name ?: null, 'replyTo' => $store->email ?: null, 'category' => 'automation', 'storeId' => $store->id,
            ]));

            if ($result->ok()) {
                $row->forceFill(['status' => 'sent', 'sent_at' => now(), 'provider' => $result->provider->driver, 'message_id' => $result->messageId, 'error' => null, 'attempts' => $row->attempts + 1])->save();
            } elseif ($result->status === 'failed') {
                $row->forceFill(['status' => 'failed', 'error' => $result->error, 'attempts' => $row->attempts + 1])->save();
            } else {
                $noProvider = str_contains((string) $result->error, 'No email provider') || str_contains((string) $result->error, 'switched off');
                $attempts = $noProvider ? $row->attempts : $row->attempts + 1;
                $row->forceFill([
                    'status' => $noProvider ? 'waiting_for_provider' : 'retrying', 'error' => $result->error, 'attempts' => $attempts,
                    'next_attempt_at' => now()->addMinutes($noProvider ? 5 : self::BACKOFF[min($attempts, count(self::BACKOFF)) - 1] ?? 480),
                ])->save();
            }
            $counts[$result->ok() ? 'sent' : ($result->status === 'failed' ? 'failed' : 'waiting')]++;
        }

        $this->info(collect($counts)->map(fn ($n, $k) => "{$n} {$k}")->implode(', '));

        return self::SUCCESS;
    }
}
