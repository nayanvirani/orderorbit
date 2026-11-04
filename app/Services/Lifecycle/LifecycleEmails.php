<?php

namespace App\Services\Lifecycle;

use App\Models\Experience;
use App\Models\Store;
use App\Services\Analytics\Analytics;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Support\Features;
use App\Support\Plans;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Merchant lifecycle emails (G24) to store owners: a welcome, setup tips (no widget yet, a live widget
 * not placed in the theme), an upgrade note when a store builds a draft its plan can't publish, and
 * monthly results. Each is sent once, only while its step is still undone, as a designed email with
 * the setup checklist and one button. Owners turn the tips and results off from the email or in
 * Settings › Store (the welcome is always sent).
 */
class LifecycleEmails
{
    public function __construct(private readonly EmailSender $sender, private readonly Analytics $analytics) {}

    /** Sends what's due now; returns how many emails went out. */
    public function run(): int
    {
        $sent = 0;
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->lazyById(100)->each(function (Store $store) use (&$sent) {
            if (! Features::enabled('lifecycle_emails', $store)) {
                return;
            }
            foreach ($this->due($store) as $kind => [$subject, $parts]) {
                $sent += $this->send($store, $kind, $subject, $parts);
            }
        });

        return $sent;
    }

    /**
     * kind => [subject, email parts] for the emails this store should get now.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    public function due(Store $store): array
    {
        $due = [];
        $installed = $store->installed_at;
        // The app's handle in admin URLs, as Shopify reported it on the last billing sync.
        $app = $store->adminUrl('apps/'.(\Illuminate\Support\Facades\Cache::get('shopify.app_handle') ?: config('shopify.app_handle')));
        $name = $store->name ?: $store->shop_domain;
        $experiences = $store->experiences()->notArchived();
        $first = (clone $experiences)->oldest('id')->first();
        $live = (clone $experiences)->where('status', 'published')->first();
        $steps = fn (bool $placed) => [
            ['Install Growvia', true],
            ['Create your first offer', (bool) $first],
            ['Publish it', (bool) $live],
            ['Add its block to your theme', $placed],
        ];

        // Welcome: only for stores that installed recently (not everyone when this ships).
        if ($installed->gt(now()->subDays(2))) {
            $due['welcome'] = ['Welcome to Growvia', [
                'heading' => 'Welcome to Growvia', 'intro' => "Thanks for installing Growvia on {$name}. Three steps get your first offer in front of shoppers: pick a template, publish it, and add its block in the Theme Editor.",
                'steps' => $steps(false), 'cta' => ['Create my first offer', $app], 'help' => 'Stuck? Reply to this email and we\'ll help.',
                'footer' => "You get this because {$name} installed Growvia.",
            ]];
        }
        if ($store->lifecycle_unsubscribed) {
            return $this->notSent($store, $due);
        }

        if ($installed->lte(now()->subDays(3)) && $installed->gt(now()->subDays(21)) && ! $first) {
            $due['no_widget'] = ['Your first offer takes about five minutes', [
                'heading' => 'Your first offer takes about five minutes', 'intro' => "{$name} doesn't have an offer yet. Most stores start with a bundle on their best seller or a free-shipping goal just above their average order. Every template is ready to publish: pick one, change the text, and add its block in the Theme Editor.",
                'steps' => $steps(false), 'cta' => ['Pick a template', $app], 'help' => 'Takes about five minutes. Stuck? Reply to this email and we\'ll help.',
                'footer' => "You get this because {$name} installed Growvia. This email stops once you've created an offer.",
            ]];
        }

        $unplaced = (clone $experiences)->where('status', 'published')->where('placement_status', 'not_placed')->where('published_at', '<=', now()->subDays(2))->first();
        if ($unplaced) {
            $surface = \App\Experiences\Registry::has($unplaced->type) ? \App\Experiences\Registry::type($unplaced->type)['surface'] : 'product';
            $due['not_placed'] = ['Your offer is ready, it just isn\'t on your store yet', [
                'heading' => 'One step left for “'.$unplaced->name.'”', 'intro' => "You published “{$unplaced->name}” ".$unplaced->published_at->diffForHumans().", but it isn't showing in your theme yet. Shoppers can't see it until its app block is added to your store.",
                'steps' => $steps(false), 'cta' => ['Add it to my theme', $store->themeEditorUrl($surface)],
                'help' => 'It opens the Theme Editor with the block ready to drop in. Takes about 2 minutes. Stuck? Reply to this email and we\'ll help.',
                'footer' => "You get this because {$name} installed Growvia. This email stops once the block is live.",
            ]];
        }

        $locked = (clone $experiences)->where('status', 'draft')->where('created_at', '<=', now()->subDay())->latest('id')->get()
            ->first(fn (Experience $e) => ! $store->allowsExperience($e->type, $e->draft_config));
        if ($locked && ($feature = collect(\App\Support\Modules::forExperience($locked->type, $locked->draft_config))->first(fn ($k) => ! $store->planIncludes($k)))) {
            $due['upgrade_preview'] = ['Your draft “'.$locked->name.'” is ready', [
                'heading' => 'Your draft “'.$locked->name.'” is ready', 'intro' => "You built “{$locked->name}” in preview. ".Plans::unlockMessage($feature, 'publish it', $store).' Drafts and settings stay as they are when you change plans.',
                'cta' => ['See plans', $app.'/app/settings/billing'],
                'footer' => "You get this because {$name} installed Growvia. This email is sent once.",
            ]];
        }

        // Monthly results, early in the month, for the month before (when there was something live).
        if (now()->day <= 3) {
            $from = now()->subMonthNoOverflow()->startOfMonth();
            $to = $from->copy()->endOfMonth();
            $t = $this->analytics->totals($store, $from, $to);
            if ($t['orders'] > 0 || $t['influenced_orders'] > 0) {
                $currency = $store->currency ?: 'USD';
                $money = fn (float $v) => $currency.' '.number_format($v, 2);
                $due['monthly_results:'.$from->format('Y-m')] = ['Your Growvia results for '.$from->format('F'), [
                    'heading' => 'Your results for '.$from->format('F Y'),
                    'intro' => "Orders with a Growvia offer: {$t['influenced_orders']}, worth {$money($t['influenced_revenue'])}.\nAll orders: {$t['orders']} ({$money($t['revenue'])})."
                        .($t['conversion'] !== null ? "\nConversion rate: ".number_format($t['conversion'], 2).'%.' : ''),
                    'cta' => ['See every offer\'s numbers', $app.'/app/analytics'],
                    'footer' => "You get this monthly because {$name} uses Growvia.",
                ]];
            }
        }

        return $this->notSent($store, $due);
    }

    /** After an uninstall (G27): a short goodbye with one optional question, sent once. */
    public function goodbye(Store $store): int
    {
        $name = $store->name ?: $store->shop_domain;

        return $this->send($store, 'goodbye', 'Sorry to see you go', [
            'heading' => 'Sorry to see you go', 'intro' => "Growvia has been removed from {$name}. Your offers have stopped showing to shoppers.\n\nWould you tell us why you left? It's one optional question and takes ten seconds.",
            'cta' => ['Answer one question', URL::temporarySignedRoute('goodbye', now()->addDays(30), ['store' => $store->id])],
            'footer' => "You get this once because {$name} removed Growvia.",
        ], tips: false);
    }

    /** Already sent (or being sent) kinds are left out; a failed one is tried again after an hour, for 3 days. */
    private function notSent(Store $store, array $due): array
    {
        $sent = DB::table('lifecycle_emails')->where('store_id', $store->id)
            ->where(fn ($q) => $q->where('status', '!=', 'failed')->orWhere('updated_at', '>', now()->subHour())->orWhere('created_at', '<', now()->subDays(3)))
            ->pluck('kind')->all();

        return array_diff_key($due, array_flip($sent));
    }

    private function send(Store $store, string $kind, string $subject, array $parts, bool $tips = true): int
    {
        // Claim the kind first: a second run (or worker) can't send it twice.
        DB::table('lifecycle_emails')->where('store_id', $store->id)->where('kind', $kind)->where('status', 'failed')->delete();
        try {
            $id = DB::table('lifecycle_emails')->insertGetId(['store_id' => $store->id, 'kind' => $kind, 'to_email' => '', 'status' => 'sending', 'created_at' => now(), 'updated_at' => now()]);
        } catch (QueryException) {
            return 0;
        }
        $owners = $store->activeOwners()->whereNotNull('email')->get();
        $recipients = $owners->isNotEmpty() ? $owners->map(fn ($o) => [$o->email, $o->first_name])->all() : ($store->email ? [[$store->email, null]] : []);
        $count = 0;
        $error = null;
        foreach ($recipients as [$email, $first]) {
            $email_parts = ['intro' => 'Hi'.($first ? " {$first}" : '').",\n\n".$parts['intro']] + $parts
                + ($kind === 'welcome' || ! $tips ? [] : ['unsubscribe' => URL::signedRoute('lifecycle.unsubscribe', ['store' => $store->id])]);
            try {
                $result = $this->sender->send(OutgoingEmail::designed($email, $subject, $email_parts, ['category' => 'lifecycle', 'storeId' => $store->id]));
                $result->ok() ? $count++ : $error = $result->error;
            } catch (Throwable $e) {
                report($e);
                $error = $e->getMessage();
            }
        }
        DB::table('lifecycle_emails')->where('id', $id)->update([
            'to_email' => mb_substr(implode(', ', array_column($recipients, 0)), 0, 191), 'status' => $count ? 'sent' : 'failed',
            'error' => $count ? null : mb_substr((string) ($error ?? 'No owner email address.'), 0, 1000), 'updated_at' => now(),
        ]);

        return $count;
    }
}
