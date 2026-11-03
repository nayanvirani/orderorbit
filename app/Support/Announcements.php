<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/** Announcements to merchants (G41): which ones a person sees on a page, and the owner emails. */
class Announcements
{
    /** Live announcements for this person on this page; each one shown is recorded as seen. */
    public static function forPage(Store $store, StoreUser $user, bool $home): array
    {
        $dismissed = AnnouncementView::where('store_user_id', $user->id)->whereNotNull('dismissed_at')->pluck('announcement_id')->all();
        $live = Announcement::where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->when(! $home, fn ($q) => $q->where('placement', 'every_page'))
            ->whereNotIn('id', $dismissed)->orderByDesc('starts_at')->limit(5)->get()
            ->filter(fn (Announcement $a) => $a->reaches($store, $user))->values();

        foreach ($live as $a) {
            try {
                AnnouncementView::firstOrCreate(['announcement_id' => $a->id, 'store_user_id' => $user->id], ['store_id' => $store->id, 'seen_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // Seen in a parallel request.
            }
        }

        return $live->map(fn (Announcement $a) => [
            'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'tone' => $a->tone, 'dismissible' => $a->dismissible,
            'cta' => $a->cta_text && $a->cta_url ? ['text' => $a->cta_text, 'url' => $a->cta_url, 'external' => ! str_starts_with($a->cta_url, '/')] : null,
        ])->all();
    }

    /** Emails the owners of every store in the audience, once, when an announcement starts. */
    public static function sendDueEmails(): int
    {
        $sent = 0;
        $due = Announcement::where('email_owners', true)->whereNull('emailed_at')->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->get();
        foreach ($due as $a) {
            $a->forceFill(['emailed_at' => now()])->save(); // claim it first so a slow run can't send twice
            Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->lazyById(100)->each(function (Store $store) use ($a, &$sent) {
                if (! $a->reaches($store)) {
                    return;
                }
                foreach ($store->activeOwners()->whereNotNull('email')->get() as $owner) {
                    try {
                        $text = "Hi {$owner->first_name},\n\n{$a->body}".($a->cta_url ? "\n\n{$a->cta_text}: ".(str_starts_with($a->cta_url, '/') ? 'open Growvia in your Shopify admin' : $a->cta_url) : '')."\n\nGrowvia";
                        $email = OutgoingEmail::fromText($owner->email, $a->title, $text, ['category' => 'announcement', 'storeId' => $store->id]);
                        if (app(EmailSender::class)->send($email)->ok()) {
                            $sent++;
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
        }

        return $sent;
    }
}
