<?php

namespace App\Services\Profit;

use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Support\Features;
use App\Support\StaffAccess;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Weekly summary email (G16), every Monday: last week's sales, orders and profit after ads (when
 * costs are set), with the change from the week before, plus the Health Score and the top 3 open
 * alerts when Protect is on, to the people who can see profit. Each
 * person can turn it off from the email or in Settings › Team and permissions.
 */
class WeeklySummary
{
    public function __construct(private readonly ProfitReports $reports, private readonly EmailSender $sender) {}

    public function run(): int
    {
        $sent = 0;
        Store::whereNotNull('installed_at')->whereNull('uninstalled_at')->lazyById(50)->each(function (Store $store) use (&$sent) {
            if (Features::enabled('sales_insights', $store) && ProfitSync::enabled($store)
                && ProfitSetting::where('store_id', $store->id)->whereNotNull('backfilled_at')->exists()) {
                $sent += $this->send($store);
            }
        });

        return $sent;
    }

    /** The email's parts for last week (Monday to Sunday in the store's time zone). */
    public function build(Store $store, ?Carbon $today = null): array
    {
        $today ??= now($store->timezone ?: 'UTC');
        $from = $today->copy()->startOfWeek()->subWeek();
        $to = $from->copy()->endOfWeek()->startOfDay();
        $t = $this->reports->totals($store, $from, $to);
        $p = $this->reports->totals($store, $from->copy()->subWeek(), $to->copy()->subWeek());
        $currency = $store->currency ?: 'USD';
        $money = fn (float $v) => ($v < 0 ? '−' : '').$currency.' '.number_format(abs($v), 0);
        $change = fn (float $now, float $before) => $before ? ' ('.($now >= $before ? '+' : '−').abs((int) round(($now - $before) / abs($before) * 100)).'% vs the week before)' : '';
        $costed = $t['net_sales'] > 0 && $t['missing_cost_sales'] < $t['net_sales'];
        $top = collect($this->reports->breakdown($store, $from, $to, 'product'))->sortByDesc('after_ads')->first();
        $name = $store->name ?: $store->shop_domain;
        $week = $from->format('j M').'–'.$to->format('j M');

        $lines = ["Net sales: {$money($t['net_sales'])}{$change($t['net_sales'], $p['net_sales'])}", "Orders: {$t['orders']}{$change($t['orders'], $p['orders'])}"];
        $lines[] = $costed ? "Profit after ads: {$money($t['after_ads'])}{$change($t['after_ads'], $p['after_ads'])}" : 'Profit: add product costs in Growvia to see it.';
        if ($costed && $t['margin'] !== null) {
            $lines[] = "Margin after ads: {$t['margin']}%";
        }
        if ($t['missing_cost_sales'] > 0) {
            $lines[] = "{$money($t['missing_cost_sales'])} of sales had no product cost and is left out of profit.";
        }
        if ($top && $top['after_ads'] > 0) {
            $lines[] = "Most profit: {$top['name']} ({$money($top['after_ads'])}).";
        }

        // Health Score and the top 3 open alerts (Protect, when it's on for the store).
        $actions = false;
        if (Features::enabled('protect', $store) && $store->planIncludes('protect')) {
            if (Features::enabled('protect_center', $store)) {
                $score = app(\App\Services\Protect\Health::class)->compute($store)['score'];
                if ($score !== null) {
                    $weekAgo = \App\Models\Protect\HealthScore::where('store_id', $store->id)->where('day', '<=', $today->copy()->subDays(7)->toDateString())->orderByDesc('day')->value('score');
                    $diff = $weekAgo !== null ? $score - $weekAgo : 0;
                    $lines[] = '';
                    $lines[] = "Health Score: {$score} of 100".($diff ? ' ('.($diff > 0 ? '+' : '−').abs($diff).' since last week)' : '');
                    $actions = true;
                }
            }
            $alerts = \App\Models\Protect\ProtectAlert::where('store_id', $store->id)->where('status', 'open')->where('severity', '!=', 'idea')->get()
                ->sortBy(fn ($a) => [$a->severity === 'urgent' ? 0 : 1, -$a->cost])->take(3);
            if ($alerts->isNotEmpty()) {
                $lines[] = '';
                $lines[] = 'Top '.$alerts->count().' open '.($alerts->count() === 1 ? 'alert' : 'alerts').':';
                foreach ($alerts->values() as $i => $a) {
                    $lines[] = ($i + 1).". {$a->title}: {$a->cost_text}.";
                }
                $actions = true;
            }
        }

        return [
            'subject' => "{$name} last week: {$money($t['net_sales'])} in sales".($costed ? ", {$money($t['after_ads'])} profit after ads" : ''),
            'parts' => [
                'heading' => "Week of {$week}", 'intro' => implode("\n", $lines),
                'cta' => $actions && Features::enabled('protect_center', $store)
                    ? ['Open Action Center', $store->adminUrl('apps/'.(Cache::get('shopify.app_handle') ?: config('shopify.app_handle')).'/app/protect/actions')]
                    : ['Open Profit', $store->adminUrl('apps/'.(Cache::get('shopify.app_handle') ?: config('shopify.app_handle')).'/app/profit')],
                'footer' => "Sent every Monday to people who can see profit for {$name}.",
            ],
            'week' => $from->format('o-\WW'),
        ];
    }

    private function send(Store $store): int
    {
        $email = $this->build($store);
        // Once a week per store, even if the command runs twice.
        try {
            $id = DB::table('lifecycle_emails')->insertGetId(['store_id' => $store->id, 'kind' => 'weekly:'.$email['week'], 'to_email' => '', 'status' => 'sending', 'created_at' => now(), 'updated_at' => now()]);
        } catch (QueryException) {
            return 0;
        }
        $people = $store->users()->whereNull('disabled_at')->whereNotNull('email')->where('weekly_email', true)->get()
            ->filter(fn (StoreUser $u) => StaffAccess::can($u, 'profit'));
        $count = 0;
        foreach ($people as $u) {
            try {
                $parts = ['intro' => 'Hi'.($u->first_name ? " {$u->first_name}" : '').",\n\n".$email['parts']['intro'],
                    'unsubscribe' => URL::signedRoute('weekly.off', ['user' => $u->id]), 'unsubscribe_label' => 'Turn off weekly email'] + $email['parts'];
                $result = $this->sender->send(OutgoingEmail::designed($u->email, $email['subject'], $parts, ['category' => 'weekly', 'storeId' => $store->id]));
                $result->ok() && $count++;
            } catch (Throwable $e) {
                report($e);
            }
        }
        DB::table('lifecycle_emails')->where('id', $id)->update(['to_email' => mb_substr($people->pluck('email')->implode(', '), 0, 191), 'status' => $count ? 'sent' : 'failed', 'updated_at' => now()]);

        return $count;
    }
}
