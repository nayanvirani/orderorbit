<?php

namespace App\Services\Insights;

use App\Models\AnalyticsEvent;
use App\Models\Protect\ProtectAlert;
use App\Models\Store;
use App\Services\Ai\Ai;
use App\Services\Profit\ProfitReports;
use App\Services\Profit\ProfitSync;
use App\Services\Protect\Protect;
use App\Support\Features;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AI Morning Brief (G17): a daily brief of up to 5 lines (yesterday against the usual, what
 * changed, the one thing to do today). Growvia works out the facts; the AI only writes the lines
 * and must cite the facts each line comes from, so every statement links to its number. Lines
 * citing nothing are dropped, and with nothing notable there's no brief that day.
 */
class MorningBrief
{
    /** A change counts as notable from this share. */
    public const NOTABLE = 15;

    public function __construct(private readonly ProfitReports $reports, private readonly Ai $ai, private readonly Protect $protect) {}

    public static function available(Store $store): bool
    {
        return Features::enabled('morning_brief', $store) && $store->planIncludes('morning_brief') && ProfitSync::enabled($store);
    }

    /** The facts behind yesterday's brief: id => [label, value, href, notable]. */
    public function facts(Store $store, ?Carbon $today = null): array
    {
        $today ??= now($store->timezone ?: 'UTC')->startOfDay();
        $y = $today->copy()->subDay();
        $currency = $store->currency ?: 'USD';
        $money = fn (float $v) => ($v < 0 ? '−' : '').$currency.' '.number_format(abs($v), 2);
        $t = $this->reports->totals($store, $y, $y);
        // Usual = the same weekday over the 4 weeks before.
        $usual = ['net_sales' => 0, 'orders' => 0, 'after_ads' => 0];
        for ($w = 1; $w <= 4; $w++) {
            $d = $y->copy()->subWeeks($w);
            $u = $this->reports->totals($store, $d, $d);
            foreach ($usual as $k => $_) {
                $usual[$k] += $u[$k] / 4;
            }
        }
        $change = fn (float $now, float $before) => abs($before) < 1 ? null : (int) round(($now - $before) / abs($before) * 100);
        $facts = [];
        $add = function (string $id, string $label, string $value, string $href, bool $notable) use (&$facts) {
            $facts[$id] = ['label' => $label, 'value' => $value, 'href' => $href, 'notable' => $notable];
        };
        $profitHref = route('app.profit.dashboard', [], false);
        foreach (['net_sales' => 'Net sales yesterday', 'orders' => 'Orders yesterday'] as $k => $label) {
            $c = $change($t[$k], $usual[$k]);
            $add($k, $label, ($k === 'orders' ? (string) $t[$k] : $money($t[$k])).' (usual '.($k === 'orders' ? round($usual[$k], 1) : $money($usual[$k])).($c !== null ? ', '.($c >= 0 ? '+' : '−').abs($c).'%' : '').')',
                $k === 'orders' ? route('app.profit.breakdowns', ['by' => 'order'], false) : $profitHref, $c !== null && abs($c) >= self::NOTABLE);
        }
        if ($t['net_sales'] > 0 && $t['missing_cost_sales'] < $t['net_sales']) {
            $c = $change($t['after_ads'], $usual['after_ads']);
            $add('after_ads', 'Profit after ads yesterday', $money($t['after_ads']).' (usual '.$money($usual['after_ads']).($c !== null ? ', '.($c >= 0 ? '+' : '−').abs($c).'%' : '').')', $profitHref, $c !== null && abs($c) >= self::NOTABLE);
        }
        $top = collect($this->reports->breakdown($store, $y, $y, 'product'))->sortByDesc('net_sales')->first();
        if ($top && $top['net_sales'] > 0) {
            $add('top_product', 'Best seller yesterday', "{$top['name']}: ".$money($top['net_sales']).", {$top['units']} sold", route('app.profit.breakdowns', ['by' => 'product'], false), false);
        }
        $urgent = ProtectAlert::where('store_id', $store->id)->where('status', 'open')->where('severity', 'urgent')->orderByDesc('cost')->get();
        if ($urgent->isNotEmpty()) {
            $add('urgent', 'Urgent problems', $urgent->count().': '.$urgent->first()->title.' ('.$urgent->first()->cost_text.')', route('app.protect.alerts', [], false), true);
        }
        if (! ProfitSync::missingAccess($store)) {
            $soon = array_filter($this->protect->inventory($store), fn ($r) => $r['rate'] > 0 && $r['days'] !== null && $r['days'] <= 7);
            if ($soon) {
                $first = reset($soon);
                $add('stock', 'Selling out within 7 days', count($soon).': '.$first['name'].' ('.$first['available'].' left)', route('app.protect.inventory', [], false), true);
            }
        }
        $offers = (float) AnalyticsEvent::where('store_id', $store->id)->where('event', 'attributed')
            ->whereBetween('occurred_at', [$y->copy()->setTimezone('UTC'), $y->copy()->endOfDay()->setTimezone('UTC')])->sum('value');
        if ($offers > 0) {
            $add('offers', 'Added by Growvia offers yesterday', $money($offers), route('app.analytics.revenue', [], false), false);
        }

        return $facts;
    }

    /** Makes today's brief once (ready, quiet when nothing changed, or paused without AI budget). */
    public function make(Store $store): ?string
    {
        $tz = $store->timezone ?: 'UTC';
        $day = now($tz)->toDateString();
        if (DB::table('morning_briefs')->where('store_id', $store->id)->where('day', $day)->exists()) {
            return null;
        }
        $facts = $this->facts($store);
        $status = 'quiet';
        $lines = null;
        if (collect($facts)->contains('notable', true)) {
            if (! $this->ai->available($store)) {
                $status = 'paused';
            } else {
                $lines = $this->write($store, $facts);
                $status = $lines ? 'ready' : 'failed';
            }
        }
        DB::table('morning_briefs')->insert(['store_id' => $store->id, 'day' => $day, 'status' => $status, 'lines' => $lines ? json_encode($lines) : null,
            'facts' => json_encode($facts), 'created_at' => now(), 'updated_at' => now()]);

        return $status;
    }

    /** The AI writes the lines from the facts; only lines citing known facts are kept. */
    public function write(Store $store, array $facts): ?array
    {
        $list = collect($facts)->map(fn ($f, $id) => "[{$id}] {$f['label']}: {$f['value']}".($f['notable'] ? ' (notable)' : ''))->implode("\n");
        $data = $this->ai->json($store, 'morning_brief',
            'You write a short morning brief for a Shopify store owner. Use only the facts given; never invent numbers. '
            .'Write at most 5 plain, friendly lines: how yesterday compared with the usual, what changed, and as the last line the one thing to do today. '
            .'Each line cites the ids of the facts it uses. Skip facts that are not worth mentioning.',
            "Store: ".($store->name ?: 'the store')."\nFacts about yesterday:\n{$list}",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['lines'], 'properties' => [
                'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['text', 'facts'], 'properties' => [
                    'text' => ['type' => 'string'], 'facts' => ['type' => 'array', 'items' => ['type' => 'string']],
                ]]],
            ]], 2000);
        $lines = collect($data['lines'] ?? [])
            ->map(fn ($l) => ['text' => trim((string) ($l['text'] ?? '')), 'facts' => array_values(array_intersect((array) ($l['facts'] ?? []), array_keys($facts)))])
            ->filter(fn ($l) => $l['text'] !== '' && $l['facts'])->take(5)->values()->all();

        return $lines ?: null;
    }
}
