<?php

namespace App\Services\Questions;

use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\Profit\AdExpense;
use App\Models\Profit\ProfitOrder;
use App\Models\Protect\ProtectAlert;
use App\Models\Store;
use App\Services\Profit\ProfitReports;
use App\Services\Profit\ProfitSync;
use App\Services\Protect\Health;
use App\Services\Protect\Protect;
use Illuminate\Support\Carbon;

/**
 * Answers a store question from the store's own data, with the same calculations as the Profit,
 * Protect and Insights pages: a sentence, the numbers and the table behind it. Questions that need
 * data the store hasn't added (costs, ad spend) say what to add instead of answering wrongly.
 */
class QuestionAnswerer
{
    public function __construct(private readonly ProfitReports $reports, private readonly Protect $protect) {}

    /** [sentence, numbers [[label, value, kind]], table {columns, rows}|null, needs string|null, period label|null] */
    public function answer(Store $store, array $question): array
    {
        $spec = $question['spec'];
        $this->currency = $store->currency ?: 'USD';

        return match ($spec['kind']) {
            'value', 'compare' => $this->value($store, $spec),
            'rank' => $this->rank($store, $spec),
            'offers' => $this->offers($store, $spec),
            default => $this->fixed($store, $spec['key']),
        };
    }

    private string $currency = 'USD';

    /** [from, to, previous from, previous to] in the store's time zone. */
    public function period(Store $store, string $key): array
    {
        $today = now($store->timezone ?: 'UTC')->startOfDay();
        [$from, $to] = match ($key) {
            'today' => [$today->copy(), $today->copy()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()],
            'last_week' => [$today->copy()->startOfWeek()->subWeek(), $today->copy()->startOfWeek()->subDay()],
            'last_7' => [$today->copy()->subDays(6), $today->copy()],
            'last_14' => [$today->copy()->subDays(13), $today->copy()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->startOfMonth()->subDay()],
            'last_90' => [$today->copy()->subDays(89), $today->copy()],
            'this_quarter' => [$today->copy()->startOfQuarter(), $today->copy()],
            'last_quarter' => [$today->copy()->startOfQuarter()->subQuarterNoOverflow(), $today->copy()->startOfQuarter()->subDay()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->startOfYear()->subDay()],
            'last_12m' => [$today->copy()->subMonthsNoOverflow(12)->addDay(), $today->copy()],
            default => [$today->copy()->subDays(29), $today->copy()], // last_30
        };
        $days = $from->diffInDays($to) + 1;

        return [$from, $to, $from->copy()->subDays($days), $from->copy()->subDay()];
    }

    private function value(Store $store, array $spec): array
    {
        [$label, , $kind, , $needs] = QuestionLibrary::MEASURES[$spec['measure']];
        $words = QuestionLibrary::PERIODS[$spec['period']];
        [$from, $to, $pFrom, $pTo] = $this->period($store, $spec['period']);
        if ($blocked = $this->blocked($store, $needs, $from, $to)) {
            return $blocked;
        }
        $now = $this->measure($store, $spec['measure'], $from, $to);
        $range = $this->range($from, $to);
        if ($spec['kind'] === 'value') {
            return $this->result(ucfirst($label).' '.$words.': '.$this->fmt($now, $kind).'.', [[ucfirst($label), $now, $kind]], null, null, $range);
        }
        $before = $this->measure($store, $spec['measure'], $pFrom, $pTo);
        $diff = $now !== null && $before !== null ? $now - $before : null;
        $pct = $diff !== null && $before ? round($diff / abs($before) * 100) : null;
        $sentence = ucfirst($label).' '.$words.' '.($diff === null ? 'can\'t be compared yet.' : ($diff == 0 ? 'was the same as the period before ('.$this->fmt($before, $kind).').'
            : ($diff > 0 ? 'rose' : 'fell').' from '.$this->fmt($before, $kind).' to '.$this->fmt($now, $kind).($kind === 'percent' ? ' ('.($diff > 0 ? '+' : '−').abs(round($diff, 1)).' pts).' : ($pct !== null ? ' ('.($diff > 0 ? '+' : '−').abs($pct).'%).' : '.'))));

        return $this->result($sentence, [['Now', $now, $kind], ['Period before', $before, $kind], ['Change', $kind === 'percent' ? $diff : $pct, $kind === 'percent' ? 'points' : 'percent']], null, null,
            $range.' vs '.$this->range($pFrom, $pTo));
    }

    private function measure(Store $store, string $m, Carbon $from, Carbon $to): ?float
    {
        if (in_array($m, ['new_customers', 'returning_share'], true)) {
            $orders = ProfitOrder::where('store_id', $store->id)->where('excluded', false)->whereBetween('day', [$from->toDateString(), $to->toDateString()]);
            $all = (clone $orders)->count();
            $returning = (clone $orders)->where('returning', true)->count();

            return $m === 'returning_share' ? ($all ? round($returning / $all * 100, 1) : null)
                : (float) (clone $orders)->where('returning', false)->whereNotNull('customer_id')->distinct()->count('customer_id');
        }
        $t = $this->reports->totals($store, $from, $to);

        return match ($m) {
            'aov' => $t['orders'] ? round($t['net_sales'] / $t['orders'], 2) : null,
            'margin' => $t['margin'],
            default => (float) ($t[$m] ?? 0),
        };
    }

    private function rank(Store $store, array $spec): array
    {
        [$plural, , $measures] = QuestionLibrary::RANKINGS[$spec['dim']];
        [$from, $to] = $this->period($store, $spec['period']);
        $needsCosts = in_array($spec['measure'], ['after_ads', 'margin'], true);
        if ($blocked = $this->blocked($store, $needsCosts ? 'costs' : null, $from, $to)) {
            return $blocked;
        }
        $rows = $spec['dim'] === 'weekday' ? $this->weekdays($store, $from, $to) : $this->reports->breakdown($store, $from, $to, $spec['dim']);
        if ($needsCosts) {
            $rows = array_values(array_filter($rows, fn ($r) => empty($r['missing_cost'])));
        }
        $rows = collect($rows)->filter(fn ($r) => $r[$spec['measure']] !== null && ($spec['measure'] !== 'after_ads' || $spec['dir'] === 'desc' || $r['after_ads'] < 0))
            ->sortBy($spec['measure'], SORT_REGULAR, $spec['dir'] === 'desc')->take(10)->values();
        $range = $this->range($from, $to);
        if ($rows->isEmpty()) {
            return $this->result($spec['measure'] === 'after_ads' && $spec['dir'] === 'asc' ? "No {$plural} lost money ".QuestionLibrary::PERIODS[$spec['period']].'.' : 'No sales '.QuestionLibrary::PERIODS[$spec['period']].' yet.', [], null, null, $range);
        }
        $kind = QuestionLibrary::MEASURES[$spec['measure']][2] ?? 'number';
        $first = $rows[0];
        $sentence = "{$first['name']}: ".$this->fmt($first[$spec['measure']], $kind).' '.(QuestionLibrary::MEASURES[$spec['measure']][0] ?? $spec['measure']).' '.QuestionLibrary::PERIODS[$spec['period']].'.';
        $singular = ['product' => 'Product', 'variant' => 'Variant', 'country' => 'Country', 'channel' => 'Sales channel', 'day' => 'Day', 'weekday' => 'Weekday', 'order' => 'Order'][$spec['dim']];
        $table = ['columns' => [$singular, 'Net sales', 'Orders', 'Units', 'Profit after ads', 'Margin'],
            'kinds' => ['text', 'money', 'number', 'number', 'money', 'percent'],
            'rows' => $rows->map(fn ($r) => [$r['name'], $r['net_sales'], $r['orders'], $r['units'] ?? null, empty($r['missing_cost']) ? ($r['after_ads'] ?? null) : null, empty($r['missing_cost']) ? ($r['margin'] ?? null) : null])->all()];

        return $this->result($sentence, [[ucfirst(QuestionLibrary::MEASURES[$spec['measure']][0] ?? $spec['measure']), $first[$spec['measure']], $kind]], $table, null, $range);
    }

    /** Sales and orders by day of the week. */
    private function weekdays(Store $store, Carbon $from, Carbon $to): array
    {
        $out = [];
        foreach ($this->reports->breakdown($store, $from, $to, 'day') as $d) {
            $name = Carbon::parse($d['name'])->format('l');
            $out[$name] ??= ['name' => $name, 'net_sales' => 0, 'orders' => 0, 'units' => 0, 'after_ads' => null, 'margin' => null, 'missing_cost' => true];
            $out[$name]['net_sales'] = round($out[$name]['net_sales'] + $d['net_sales'], 2);
            $out[$name]['orders'] += $d['orders'];
            $out[$name]['units'] += $d['units'];
        }

        return array_values($out);
    }

    private function offers(Store $store, array $spec): array
    {
        [$from, $to] = $this->period($store, $spec['period']);
        $tz = $store->timezone ?: 'UTC';
        $rows = AnalyticsEvent::where('store_id', $store->id)->where('event', 'attributed')->whereNotNull('experience_handle')
            ->whereBetween('occurred_at', [$from->copy()->setTimezone('UTC'), $to->copy()->endOfDay()->setTimezone('UTC')])
            ->selectRaw('experience_handle as handle, coalesce(sum(value), 0) as total, count(*) as n')->groupBy('experience_handle')->orderByDesc('total')->get();
        $names = Experience::where('store_id', $store->id)->whereIn('handle', $rows->pluck('handle'))->pluck('name', 'handle');
        $total = round((float) $rows->sum('total'), 2);
        $words = QuestionLibrary::PERIODS[$spec['period']];
        $table = $rows->isEmpty() ? null : ['columns' => ['Offer', 'Sales added', 'Times'], 'kinds' => ['text', 'money', 'number'],
            'rows' => $rows->take(10)->map(fn ($r) => [$names[$r->handle] ?? $r->handle, round((float) $r->total, 2), (int) $r->n])->all()];
        $sentence = $spec['mode'] === 'total' || $rows->isEmpty()
            ? "Growvia's offers added ".$this->fmt($total, 'money')." in sales {$words}."
            : ($names[$rows[0]->handle] ?? $rows[0]->handle).' earned the most '.$words.': '.$this->fmt((float) $rows[0]->total, 'money').'.';

        return $this->result($sentence, [['Sales added by offers', $total, 'money']], $table, null, $this->range($from, $to));
    }

    private function fixed(Store $store, string $key): array
    {
        if (str_starts_with($key, 'stock_') || str_starts_with($key, 'data_')) {
            if (ProfitSync::missingAccess($store)) {
                return $this->needs('access');
            }
        }
        if (str_starts_with($key, 'stock_')) {
            $rows = $this->protect->inventory($store);
            [$filter, $sentence] = match ($key) {
                'stock_out_7', 'stock_out_14', 'stock_out_30' => [fn ($r) => $r['rate'] > 0 && $r['days'] !== null && $r['days'] <= (int) substr($key, 10), null],
                'stock_sold_out' => [fn ($r) => $r['status'] === 'sold_out', null],
                'stock_reorder' => [fn ($r) => in_array($r['status'], ['reorder', 'sold_out'], true), null],
                'stock_idle' => [fn ($r) => $r['status'] === 'no_sales' && $r['available'] > 0, null],
                'stock_year' => [fn ($r) => $r['over_year'], null],
                default => [fn ($r) => true, null],
            };
            if (in_array($key, ['stock_value', 'stock_idle_value'], true)) {
                $pick = $key === 'stock_value' ? $rows : array_filter($rows, fn ($r) => $r['status'] === 'no_sales');
                $value = round(array_sum(array_column($pick, 'stock_value')), 2);

                return $this->result(($key === 'stock_value' ? 'Your tracked stock is worth ' : 'Stock that hasn\'t sold in 30 days is worth ').$this->fmt($value, 'money').' at cost.',
                    [['Stock value at cost', $value, 'money']], null, null, null);
            }
            $hits = array_values(array_filter($rows, $filter));
            $table = $hits ? ['columns' => ['Product', 'In stock', 'Sold a day', 'Lasts (days)', 'Order'], 'kinds' => ['text', 'number', 'number', 'number', 'number'],
                'rows' => array_map(fn ($r) => [$r['name'], $r['available'], $r['rate'], $r['days'], $r['qty']], array_slice($hits, 0, 25))] : null;

            return $this->result(count($hits) ? count($hits).' '.(count($hits) === 1 ? 'product' : 'products').'.' : 'None right now.', [['Products', count($hits), 'number']], $table, null, null);
        }
        if (str_starts_with($key, 'data_')) {
            $issue = substr($key, 5);
            $hits = array_values(array_filter($this->protect->productData($store), fn ($p) => isset($p['issues'][$issue])));
            $table = $hits ? ['columns' => ['Product', 'Sales, 30 days'], 'kinds' => ['text', 'money'], 'rows' => array_map(fn ($p) => [$p['name'], $p['sales']], array_slice($hits, 0, 25))] : null;

            return $this->result(count($hits) ? count($hits).' '.(count($hits) === 1 ? 'product' : 'products').', best sellers first.' : 'Every product has one.', [['Products', count($hits), 'number']], $table, null, null);
        }
        if ($key === 'health') {
            $score = app(Health::class)->compute($store)['score'];

            return $this->result($score === null ? 'Not enough data to score yet.' : "Your Health Score is {$score} out of 100 (".Health::label($score).').', [['Health Score', $score, 'number']], null, null, null);
        }
        $alerts = ProtectAlert::where('store_id', $store->id)->where('status', 'open')->where('severity', '!=', 'idea')
            ->when($key === 'alerts_urgent', fn ($q) => $q->where('severity', 'urgent'))->orderByDesc('cost')->get();
        $table = $alerts->isEmpty() ? null : ['columns' => ['Problem', 'What it costs', 'How to fix'], 'kinds' => ['text', 'text', 'text'],
            'rows' => $alerts->take(15)->map(fn ($a) => [$a->title, $a->cost_text, $a->fix])->all()];

        return $this->result($alerts->isEmpty() ? 'Nothing open right now.' : $alerts->count().' open '.($alerts->count() === 1 ? 'problem' : 'problems').'.', [['Open problems', $alerts->count(), 'number']], $table, null, null);
    }

    /** Data the question needs that the store hasn't added. */
    private function blocked(Store $store, ?string $needs, Carbon $from, Carbon $to): ?array
    {
        if (ProfitSync::missingAccess($store)) {
            return $this->needs('access');
        }
        if ($needs === 'costs') {
            $t = $this->reports->totals($store, $from, $to);
            if ($t['net_sales'] > 0 && $t['missing_cost_sales'] >= $t['net_sales']) {
                return $this->needs('costs');
            }
        }
        if ($needs === 'ads' && ! AdExpense::where('store_id', $store->id)->exists()) {
            return $this->needs('ads');
        }

        return null;
    }

    private function needs(string $what): array
    {
        return $this->result(match ($what) {
            'costs' => 'This needs product costs. Add them in Profit › Costs (or set "Cost per item" in Shopify).',
            'ads' => 'This needs your ad spend. Add it in Profit › Ad expenses.',
            default => 'This needs access to your orders and product costs. Approve it in Profit.',
        }, [], null, $what, null);
    }

    private function result(string $sentence, array $numbers, ?array $table, ?string $needs, ?string $range): array
    {
        return ['sentence' => $sentence, 'numbers' => array_map(fn ($n) => ['label' => $n[0], 'value' => $n[1], 'kind' => $n[2]], $numbers),
            'table' => $table, 'needs' => $needs, 'range' => $range, 'currency' => $this->currency];
    }

    private function range(Carbon $from, Carbon $to): string
    {
        return $from->equalTo($to) ? $from->format('j M Y') : $from->format('j M').' – '.$to->format('j M Y');
    }

    private function fmt(?float $v, string $kind): string
    {
        return match (true) {
            $v === null => '—',
            $kind === 'money' => ($v < 0 ? '−' : '').$this->currency.' '.number_format(abs($v), 2),
            $kind === 'percent' => round($v, 1).'%',
            default => number_format($v, fmod($v, 1) ? 1 : 0),
        };
    }
}
