<?php

namespace App\Support;

use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Emails the Growvia team (Internal Admin › Email providers › "Team alerts go to"). With a key,
 * the same alert is sent at most once per cooldown, so a failing system doesn't flood the inbox.
 */
class TeamAlert
{
    /** Where team alerts go: the team alert address, else the reply-to, else the sender address. */
    public static function recipient(): ?string
    {
        $s = EmailSettings::get();
        foreach ([$s['team_email'] ?? '', $s['reply_to'] ?? '', $s['from_email'] ?? ''] as $to) {
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return $to;
            }
        }

        return null;
    }

    /** True when the alert was handed to an email provider. */
    public static function send(string $subject, string $text, ?string $key = null, int $cooldownMinutes = 60, ?string $replyTo = null): bool
    {
        $to = self::recipient();
        if ($to === null) {
            return false;
        }
        if ($key !== null && ! Cache::add('team-alert:'.$key, true, now()->addMinutes($cooldownMinutes))) {
            return false;
        }
        try {
            $email = OutgoingEmail::fromText($to, $subject, $text, ['category' => 'team_alert']);
            $email->replyTo = $replyTo;

            return app(EmailSender::class)->send($email)->ok();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
