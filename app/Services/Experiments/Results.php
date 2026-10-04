<?php

namespace App\Services\Experiments;

use App\Models\AnalyticsEvent;
use App\Models\Experiments\Experiment;
use Illuminate\Support\Carbon;

/**
 * Results of an A/B test from the pixel's events. A visitor belongs to the variant of their first
 * exposure; their orders, adds to cart and checkouts after that moment (until the test ends)
 * count for it. Each variant is compared with the control using Stats; a winner is only named
 * once the minimum duration and sample are reached and the primary metric is significant.
 */
class Results
{
    public function for(Experiment $experiment): array
    {
        $variants = $experiment->variants;
        $keys = $variants->pluck('key')->all();
        $from = $experiment->started_at ?? now();
        $to = $experiment->ended_at ?? now();
        $handle = $experiment->experience->handle;

        $empty = fn () => ['visitors' => 0, 'conversions' => 0, 'orders' => 0, 'revenue' => 0.0, 'units' => 0, 'rpv' => ['n' => 0, 'sum' => 0.0, 'sumsq' => 0.0], 'aov' => ['n' => 0, 'sum' => 0.0, 'sumsq' => 0.0],
            'add_to_cart' => 0, 'checkout_started' => 0, 'accepts' => 0, 'declines' => 0, 'closes' => 0, 'bundles' => 0, 'abandoned' => 0, 'devices' => [], 'daily' => []];
        $stats = array_fill_keys($keys, null);
        foreach ($keys as $key) {
            $stats[$key] = $empty();
        }

        // Each visitor's first exposure.
        $exposed = [];
        $rows = AnalyticsEvent::where('store_id', $experiment->store_id)->where('experiment_handle', $experiment->handle)->where('event', 'expose')
            ->whereNotNull('visitor_id')->whereBetween('occurred_at', [$from, $to])->orderBy('occurred_at')->orderBy('id')
            ->cursor();
        foreach ($rows as $row) {
            if (! isset($exposed[$row->visitor_id]) && isset($stats[$row->variant])) {
                $exposed[$row->visitor_id] = ['variant' => $row->variant, 'at' => $row->occurred_at, 'device' => $row->device ?: 'unknown'];
            }
        }

        // What each exposed visitor did afterwards.
        $people = [];
        foreach (array_chunk(array_keys($exposed), 500) as $chunk) {
            $events = AnalyticsEvent::where('store_id', $experiment->store_id)->whereIn('visitor_id', $chunk)->whereBetween('occurred_at', [$from, $to])
                ->where(fn ($q) => $q->whereIn('event', ['order'])->orWhereIn('name', ['product_added_to_cart', 'checkout_started'])
                    ->orWhere(fn ($q) => $q->where('experience_handle', $handle)->whereIn('event', ['add', 'accept', 'decline', 'close'])))
                ->get(['visitor_id', 'event', 'name', 'value', 'quantity', 'occurred_at', 'experience_handle']);
            foreach ($events as $e) {
                if ($e->occurred_at->lt($exposed[$e->visitor_id]['at'])) {
                    continue;
                }
                $p = &$people[$e->visitor_id];
                $p ??= ['orders' => [], 'units' => 0, 'atc' => false, 'checkout' => false, 'accepts' => 0, 'declines' => 0, 'closes' => 0, 'bundle' => false];
                match (true) {
                    $e->event === 'order' => [$p['orders'][] = (float) $e->value, $p['units'] += $e->quantity],
                    $e->name === 'product_added_to_cart' || $e->event === 'add' => $p['atc'] = true,
                    $e->name === 'checkout_started' => $p['checkout'] = true,
                    $e->event === 'accept' => $p['accepts']++,
                    $e->event === 'decline' => $p['declines']++,
                    $e->event === 'close' => $p['closes']++,
                    default => null,
                };
                if ($e->name === 'orderorbit:bundle_completed') {
                    $p['bundle'] = true;
                }
                unset($p);
            }
        }

        foreach ($exposed as $visitor => $x) {
            $s = &$stats[$x['variant']];
            $p = $people[$visitor] ?? ['orders' => [], 'units' => 0, 'atc' => false, 'checkout' => false, 'accepts' => 0, 'declines' => 0, 'closes' => 0, 'bundle' => false];
            $revenue = array_sum($p['orders']);
            $converted = $p['orders'] !== [];
            $s['visitors']++;
            $s['conversions'] += $converted ? 1 : 0;
            $s['orders'] += count($p['orders']);
            $s['revenue'] += $revenue;
            $s['units'] += $p['units'];
            $s['rpv'] = ['n' => $s['rpv']['n'] + 1, 'sum' => $s['rpv']['sum'] + $revenue, 'sumsq' => $s['rpv']['sumsq'] + $revenue * $revenue];
            foreach ($p['orders'] as $value) {
                $s['aov'] = ['n' => $s['aov']['n'] + 1, 'sum' => $s['aov']['sum'] + $value, 'sumsq' => $s['aov']['sumsq'] + $value * $value];
            }
            $s['add_to_cart'] += $p['atc'] ? 1 : 0;
            $s['checkout_started'] += $p['checkout'] ? 1 : 0;
            $s['abandoned'] += $p['atc'] && ! $converted ? 1 : 0;
            $s['accepts'] += $p['accepts'];
            $s['declines'] += $p['declines'];
            $s['closes'] += $p['closes'];
            $s['bundles'] += $p['bundle'] ? 1 : 0;
            $s['devices'][$x['device']] ??= ['visitors' => 0, 'conversions' => 0];
            $s['devices'][$x['device']]['visitors']++;
            $s['devices'][$x['device']]['conversions'] += $converted ? 1 : 0;
            $day = $x['at']->toDateString();
            $s['daily'][$day] ??= ['visitors' => 0, 'conversions' => 0];
            $s['daily'][$day]['visitors']++;
            $s['daily'][$day]['conversions'] += $converted ? 1 : 0;
            unset($s);
        }

        $alpha = Stats::alpha(count($keys));
        $metrics = [];
        foreach ($stats as $key => $s) {
            $metrics[$key] = $this->metrics($s);
        }
        $comparisons = [];
        foreach (array_diff($keys, ['A']) as $key) {
            $a = $stats['A'];
            $b = $stats[$key];
            $comparisons[$key] = [
                'conversion_rate' => Stats::proportions($a['visitors'], $a['conversions'], $b['visitors'], $b['conversions'], $alpha),
                'revenue_per_visitor' => Stats::welch($a['rpv'], $b['rpv'], $alpha),
                'aov' => Stats::welch($a['aov'], $b['aov'], $alpha),
                'guardrails' => $this->guardrails($experiment, $metrics['A'], $metrics[$key]),
            ];
        }

        return [
            'variants' => $metrics,
            'comparisons' => $comparisons,
            'alpha' => $alpha,
            'confidence' => 1 - $alpha,
            'days' => $experiment->daysRunning(),
            'decision' => $this->decide($experiment, $stats, $comparisons, $alpha),
            'daily' => $this->cumulative($stats, $from, $to),
        ];
    }

    private function metrics(array $s): array
    {
        $rate = fn ($n) => $s['visitors'] ? $n / $s['visitors'] * 100 : null;

        return [
            'visitors' => $s['visitors'],
            'conversions' => $s['conversions'],
            'orders' => $s['orders'],
            'revenue' => round($s['revenue'], 2),
            'conversion_rate' => $rate($s['conversions']),
            'revenue_per_visitor' => $s['visitors'] ? $s['revenue'] / $s['visitors'] : null,
            'aov' => $s['orders'] ? $s['revenue'] / $s['orders'] : null,
            'units_per_order' => $s['orders'] ? $s['units'] / $s['orders'] : null,
            'add_to_cart' => $rate($s['add_to_cart']),
            'checkout_started' => $rate($s['checkout_started']),
            'purchase' => $rate($s['conversions']),
            'upsell_acceptance' => ($s['accepts'] + $s['declines']) ? $s['accepts'] / ($s['accepts'] + $s['declines']) * 100 : null,
            'bundle_completion' => $rate($s['bundles']),
            'cart_abandonment' => $s['add_to_cart'] ? $s['abandoned'] / $s['add_to_cart'] * 100 : null,
            'negative_interactions' => $rate($s['closes'] + $s['declines']),
            'devices' => $s['devices'],
        ];
    }

    /** Guardrails: a variant may not worsen a rate by more than its threshold (percentage points). */
    private function guardrails(Experiment $experiment, array $control, array $variant): array
    {
        $out = [];
        foreach ($experiment->guardrails ?? [] as $g) {
            $a = $control[$g['metric']] ?? null;
            $b = $variant[$g['metric']] ?? null;
            $change = $a !== null && $b !== null ? $b - $a : null;
            $out[$g['metric']] = ['threshold' => $g['threshold'], 'change' => $change, 'breached' => $change !== null && $change > $g['threshold']];
        }

        return $out;
    }

    /**
     * collecting | winner | control | no_winner | guardrail, with a plain-language headline.
     */
    private function decide(Experiment $experiment, array $stats, array $comparisons, float $alpha): array
    {
        $metric = $experiment->primary_metric === 'conversion_rate' ? 'conversion_rate' : 'revenue_per_visitor';
        $days = $experiment->daysRunning();
        $short = [];
        if ($days < $experiment->min_days) {
            $short[] = 'at least '.$experiment->min_days.' days (now '.floor($days).')';
        }
        $fewest = min(array_column($stats, 'visitors') ?: [0]);
        if ($fewest < $experiment->min_visitors) {
            $short[] = number_format($experiment->min_visitors).' visitors per variant (lowest now '.number_format($fewest).')';
        }
        $fewestConv = min(array_column($stats, 'conversions') ?: [0]);
        if ($fewestConv < $experiment->min_conversions) {
            $short[] = number_format($experiment->min_conversions).' conversions per variant (lowest now '.number_format($fewestConv).')';
        }
        $progress = min(1, $days / max(1, $experiment->min_days), $fewest / max(1, $experiment->min_visitors), $fewestConv / max(1, $experiment->min_conversions));

        if ($short) {
            return ['state' => 'collecting', 'winner' => null, 'progress' => $progress, 'headline' => 'Collecting data. No winner until the test reaches '.implode(', ', $short).'.'];
        }

        $better = collect($comparisons)->filter(fn ($c) => ($c[$metric]['p'] ?? 1) < $alpha && ($c[$metric]['diff'] ?? 0) > 0)->sortByDesc(fn ($c) => $c[$metric]['lift'] ?? 0);
        $worse = collect($comparisons)->filter(fn ($c) => ($c[$metric]['p'] ?? 1) < $alpha && ($c[$metric]['diff'] ?? 0) < 0);
        if ($better->isNotEmpty()) {
            $key = $better->keys()->first();
            $c = $better->first();
            $breached = collect($c['guardrails'])->filter(fn ($g) => $g['breached'])->keys();
            if ($breached->isNotEmpty()) {
                return ['state' => 'guardrail', 'winner' => null, 'progress' => 1, 'headline' => "Variant {$key} improves the primary metric but breaks a guardrail (".$breached->map(fn ($m) => strtolower(ExperimentManager::GUARDRAILS[$m] ?? $m))->implode(', ').'), so it isn\'t declared the winner.'];
            }

            return ['state' => 'winner', 'winner' => $key, 'progress' => 1, 'headline' => "Variant {$key} wins: ".sprintf('%+.1f%%', $c[$metric]['lift'] ?? 0).' '.strtolower(ExperimentManager::PRIMARY[$experiment->primary_metric]).' vs control, p = '.self::p($c[$metric]['p']).'.'];
        }
        if ($worse->count() === count($comparisons) && $worse->isNotEmpty()) {
            return ['state' => 'control', 'winner' => 'A', 'progress' => 1, 'headline' => 'The control wins: every variant did significantly worse.'];
        }

        return ['state' => 'no_winner', 'winner' => null, 'progress' => 1, 'headline' => 'No clear winner: the difference isn\'t statistically significant at '.round((1 - $alpha) * 100, 1).'% confidence.'];
    }

    public static function p(?float $p): string
    {
        return $p === null ? '—' : ($p < 0.001 ? '< 0.001' : number_format($p, 3));
    }

    /** Cumulative conversion rate per variant by day of exposure. */
    private function cumulative(array $stats, Carbon $from, Carbon $to): array
    {
        $out = [];
        foreach ($stats as $key => $s) {
            [$v, $c] = [0, 0];
            for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
                $day = $d->toDateString();
                $v += $s['daily'][$day]['visitors'] ?? 0;
                $c += $s['daily'][$day]['conversions'] ?? 0;
                $out[$key][$day] = $v ? round($c / $v * 100, 2) : 0;
            }
        }

        return $out;
    }
}
