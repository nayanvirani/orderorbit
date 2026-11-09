<?php

namespace App\Services\Profit;

use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Support\Features;
use App\Support\StaffAccess;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Profit goals and pacing (G6): a monthly goal for profit after ads, contribution or margin. Each
 * day shows the goal, the month so far, the projected month-end at the current pace and what's
 * needed per day from here; an email goes once a month when the projection falls behind by the
 * chosen share. Numbers come from the same daily summary as the Profit dashboard.
 */
class ProfitGoals
{
    public const TYPES = ['after_ads' => 'Profit after ads', 'contribution' => 'Contribution', 'margin' => 'Margin after ads (%)'];

    public const ALERTS = [10, 15, 20];

    /** Emails wait until this day of the month: the first days swing too much to judge. */
    public const ALERT_FROM_DAY = 7;

    public function __construct(private readonly ProfitReports $reports, private readonly EmailSender $sender) {}

    public static function goal(Store $store): ?array
    {
        $g = ProfitSetting::for($store->id)->goal;

        return $g && ($g['amount'] ?? null) !== null ? $g + ['type' => 'after_ads', 'alert' => 10] : null;
    }

    /** This month's pacing for the store's goal, or null without a goal. */
    public function status(Store $store, ?\Carbon\Carbon $today = null): ?array
    {
        $goal = self::goal($store);
        if (! $goal) {
            return null;
        }
        $today ??= now($store->timezone ?: 'UTC')->startOfDay();
        $from = $today->copy()->startOfMonth();
        $days = $from->daysInMonth;
        // Days counted so far: up to yesterday (today isn't over), at least one.
        $elapsed = max(1, $today->day - 1);
        $to = $from->copy()->addDays($elapsed - 1);
        $t = $this->reports->totals($store, $from, $to);
        $series = $this->reports->daily($store, $from, $to);
        $amount = (float) $goal['amount'];

        if ($goal['type'] === 'margin') {
            $actual = $t['margin'];
            $projected = $actual; // a margin doesn't add up over the month
            $behind = $actual === null || $amount <= 0 ? null : round(max(0, ($amount - $actual) / $amount * 100), 1);
            $need = null;
            $cumulative = [];
        } else {
            $key = $goal['type'] === 'contribution' ? 'contribution' : 'after_ads';
            $actual = $t[$key];
            $projected = round($actual / $elapsed * $days, 2);
            $behind = $amount > 0 ? round(max(0, ($amount - $projected) / $amount * 100), 1) : null;
            $left = $days - $elapsed;
            $need = $left > 0 ? round(max(0, $amount - $actual) / $left, 2) : null;
            $sum = 0;
            $cumulative = array_map(function ($d) use (&$sum, $key) {
                $sum += $d[$key];

                return ['day' => $d['day'], 'total' => round($sum, 2)];
            }, $series);
        }

        return [
            'goal' => $goal, 'label' => self::TYPES[$goal['type']], 'month' => $from->format('F'), 'days' => $days, 'elapsed' => $elapsed,
            'actual' => $actual, 'projected' => $projected, 'behind' => $behind, 'need' => $need,
            'per_day' => $goal['type'] === 'margin' ? null : round($actual / $elapsed, 2),
            'goal_share' => $goal['type'] !== 'margin' && $amount > 0 ? (int) round($actual / $amount * 100) : null,
            'month_share' => (int) round($elapsed / $days * 100),
            'on_track' => $behind !== null && $behind < 1,
            'cumulative' => $cumulative,
        ];
    }

    /** Emails the people who can see profit once a month when the month falls behind. Run daily. */
    public function alert(Store $store): bool
    {
        $s = $this->status($store);
        $today = now($store->timezone ?: 'UTC');
        if (! $s || $today->day < self::ALERT_FROM_DAY || $s['behind'] === null || $s['behind'] < (int) $s['goal']['alert']
            || ($s['goal']['emailed'] ?? null) === $today->format('Y-m')) {
            return false;
        }
        $settings = ProfitSetting::for($store->id);
        $settings->forceFill(['goal' => array_merge($settings->goal, ['emailed' => $today->format('Y-m')])])->save();

        $currency = $store->currency ?: 'USD';
        $v = fn ($n) => $s['goal']['type'] === 'margin' ? $n.'%' : $currency.' '.number_format((float) $n, 0);
        $name = $store->name ?: $store->shop_domain;
        $intro = "At this pace {$s['month']} ends at {$v($s['projected'])}, {$s['behind']}% short of your {$v($s['goal']['amount'])} {$s['label']} goal."
            .($s['need'] !== null ? " You need about {$v($s['need'])} a day from here, up from {$v($s['per_day'])}." : '');
        $app = $store->adminUrl('apps/'.(Cache::get('shopify.app_handle') ?: config('shopify.app_handle')).'/app/profit/goals');
        $people = $store->users()->whereNull('disabled_at')->whereNotNull('email')->get()->filter(fn (StoreUser $u) => StaffAccess::can($u, 'profit'));
        foreach ($people as $u) {
            try {
                $this->sender->send(OutgoingEmail::designed($u->email, "{$name}: {$s['month']} is {$s['behind']}% behind your profit goal", [
                    'heading' => "{$s['month']} is behind your goal", 'intro' => 'Hi'.($u->first_name ? " {$u->first_name}" : '').",\n\n{$intro}",
                    'cta' => ['See your goal', $app], 'footer' => 'Sent at most once a month, to people who can see profit. Change the goal or the warning level in Profit › Goals.',
                ], ['category' => 'profit_goal', 'storeId' => $store->id]));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return true;
    }

    public static function available(Store $store): bool
    {
        return Features::enabled('profit_goals', $store) && ProfitSync::enabled($store) && $store->planIncludes('profit_goals');
    }
}
