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
 * monthly results. Each is sent once, only while its step is still undone; owners can turn the tips
 * and results off with the link in every email (the welcome is always sent).
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
            foreach ($this->due($store) as $kind => [$subject, $text]) {
                $sent += $this->send($store, $kind, $subject, $text);
            }
        });

        return $sent;
    }

    /** kind => [subject, text] for the emails this store should get now. */
    public function due(Store $store): array
    {
        $due = [];
        $installed = $store->installed_at;
        $open = 'Open Growvia: '.$store->adminUrl('apps/'.config('shopify.app_handle'));

        // Welcome: only for stores that installed recently (not everyone when this ships).
        if ($installed->gt(now()->subDays(2))) {
            $due['welcome'] = ['Welcome to Growvia', "Thanks for installing Growvia on {$this->name($store)}.\n\nThree steps get your first offer in front of shoppers:\n1. Pick a goal, and Growvia suggests where to start.\n2. Create an offer from a template: a bundle, a gift goal, a countdown or trust badges.\n3. Publish it and add its block in the Theme Editor.\n\n{$open}"];
        }
        if ($store->lifecycle_unsubscribed) {
            return $due;
        }

        $experiences = $store->experiences()->notArchived();
        if ($installed->lte(now()->subDays(3)) && $installed->gt(now()->subDays(21)) && ! (clone $experiences)->exists()) {
            $due['no_widget'] = ['Your first offer takes about five minutes', "{$this->name($store)} doesn't have an offer yet. Most stores start with a bundle on their best seller or a free-shipping goal just above their average order.\n\nEvery template is ready to publish: pick one, change the text, and add its block in the Theme Editor.\n\n{$open}"];
        }

        $unplaced = (clone $experiences)->where('status', 'published')->where('placement_status', 'not_placed')->where('published_at', '<=', now()->subDays(2))->first();
        if ($unplaced) {
            $due['not_placed'] = ['“'.$unplaced->name.'” is live but not on your store yet', "“{$unplaced->name}” is published, but its block isn't in your theme, so shoppers can't see it.\n\nOpen the Theme Editor, add the Growvia block where it should show (for example under the price on product pages), and save.\n\n{$open}"];
        }

        $locked = (clone $experiences)->where('status', 'draft')->where('created_at', '<=', now()->subDay())->latest('id')->get()
            ->first(fn (Experience $e) => ! $store->allowsExperience($e->type, $e->draft_config));
        if ($locked && ($feature = collect(\App\Support\Modules::forExperience($locked->type, $locked->draft_config))->first(fn ($k) => ! $store->planIncludes($k)))) {
            $due['upgrade_preview'] = ['Your draft “'.$locked->name.'” is ready', "You built “{$locked->name}” in preview. ".Plans::unlockMessage($feature, 'publish it', $store)."\n\nYou can change your plan in Settings › Plan and billing; drafts and settings stay as they are.\n\n{$open}"];
        }

        // Monthly results, early in the month, for the month before (when there was something live).
        if (now()->day <= 3) {
            $from = now()->subMonthNoOverflow()->startOfMonth();
            $to = $from->copy()->endOfMonth();
            $t = $this->analytics->totals($store, $from, $to);
            if ($t['orders'] > 0 || $t['influenced_orders'] > 0) {
                $currency = $store->currency ?: 'USD';
                $money = fn (float $v) => $currency.' '.number_format($v, 2);
                $due['monthly_results:'.$from->format('Y-m')] = ['Your Growvia results for '.$from->format('F'), "Here's {$from->format('F Y')} for {$this->name($store)}:\n\n"
                    ."- Orders with a Growvia offer: {$t['influenced_orders']}\n- Their sales: {$money($t['influenced_revenue'])}\n- All orders: {$t['orders']} ({$money($t['revenue'])})"
                    .($t['conversion'] !== null ? "\n- Conversion rate: ".number_format($t['conversion'], 2).'%' : '')
                    ."\n\nSee every offer's numbers in Insights.\n\n{$open}"];
            }
        }

        // Already sent (or being sent) kinds are left out; a failed one is tried again after an hour, for 3 days.
        $sent = DB::table('lifecycle_emails')->where('store_id', $store->id)
            ->where(fn ($q) => $q->where('status', '!=', 'failed')->orWhere('updated_at', '>', now()->subHour())->orWhere('created_at', '<', now()->subDays(3)))
            ->pluck('kind')->all();

        return array_diff_key($due, array_flip($sent));
    }

    private function send(Store $store, string $kind, string $subject, string $text): int
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
            $body = 'Hi'.($first ? " {$first}" : '').",\n\n{$text}\n\nThe Growvia team"
                .($kind === 'welcome' ? '' : "\n\nDon't want these tips and monthly results? Turn them off: ".URL::signedRoute('lifecycle.unsubscribe', ['store' => $store->id]));
            try {
                $result = $this->sender->send(OutgoingEmail::fromText($email, $subject, $body, ['category' => 'lifecycle', 'storeId' => $store->id]));
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

    private function name(Store $store): string
    {
        return $store->name ?: $store->shop_domain;
    }
}
