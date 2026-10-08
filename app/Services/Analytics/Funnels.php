<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Funnels: how many visitors went through event steps in order, where they dropped off and how
 * long each step took. A visitor counts for a step when they did it after the previous one,
 * within the funnel's window (the same session, or 1, 7 or 30 days from the first step).
 */
class Funnels
{
    public const WINDOWS = ['session' => 'In the same session', '1d' => 'Within 1 day', '7d' => 'Within 7 days', '30d' => 'Within 30 days'];

    public const COMPARE = ['device' => 'Device', 'source' => 'Traffic source'];

    /** At most this many events are read for one report. */
    public const MAX_EVENTS = 300_000;

    /** Ready-made funnels for the "Add a funnel" menu. */
    public const PRESETS = [
        'purchase' => ['name' => 'Store purchase funnel', 'steps' => ['product_viewed', 'product_added_to_cart', 'checkout_started', 'checkout_completed']],
        'bundle' => ['name' => 'Bundle funnel', 'steps' => ['product_viewed', 'growvia:bundle_viewed', 'growvia:bundle_completed', 'checkout_started', 'checkout_completed']],
        'upsell' => ['name' => 'Upsell funnel', 'steps' => ['growvia:upsell_viewed', 'growvia:upsell_accepted', 'checkout_completed']],
        'checkout' => ['name' => 'Checkout funnel', 'steps' => ['cart_viewed', 'checkout_started', 'payment_info_submitted', 'checkout_completed']],
    ];

    /**
     * @param  list<array{event: string, experience?: ?string}>  $steps
     * @return array{steps: list<array>, segments: array<string, list<int>>, daily: array<string, array{entered: int, completed: int}>, truncated: bool}
     */
    public function report(Store $store, array $steps, string $within, Carbon $from, Carbon $to, ?string $compare = null): array
    {
        $n = count($steps);
        $seconds = ['1d' => 86400, '7d' => 7 * 86400, '30d' => 30 * 86400][$within] ?? null;
        $reached = array_fill(0, $n, 0);
        $durations = array_fill(0, $n, []);
        $segments = [];
        $daily = [];
        $read = 0;

        $flush = function (array $events) use ($steps, $n, $seconds, $within, $compare, &$reached, &$durations, &$segments, &$daily) {
            [$best, $times, $first] = $this->walk($events, $steps, $n, $seconds, $within === 'session');
            if ($best === 0) {
                return;
            }
            for ($i = 0; $i < $best; $i++) {
                $reached[$i]++;
                if ($i > 0) {
                    $durations[$i][] = $times[$i] - $times[$i - 1];
                }
            }
            if ($compare) {
                $key = (string) ($first[$compare] ?? '') ?: 'unknown';
                $segments[$key] ??= array_fill(0, $n, 0);
                for ($i = 0; $i < $best; $i++) {
                    $segments[$key][$i]++;
                }
            }
            $day = date('Y-m-d', $times[0]);
            $daily[$day] ??= ['entered' => 0, 'completed' => 0];
            $daily[$day]['entered']++;
            $daily[$day]['completed'] += $best === $n ? 1 : 0;
        };

        $visitor = null;
        $buffer = [];
        $query = AnalyticsEvent::where('store_id', $store->id)->whereBetween('occurred_at', [$from, $to])
            ->whereIn('name', array_unique(array_column($steps, 'event')))->whereNotNull('visitor_id')
            ->orderBy('visitor_id')->orderBy('occurred_at')->orderBy('id')
            ->limit(self::MAX_EVENTS)
            ->select(['id', 'visitor_id', 'session_id', 'name', 'experience_handle', 'device', 'source', 'occurred_at']);
        foreach ($query->cursor() as $e) {
            $read++;
            if ($e->visitor_id !== $visitor && $buffer) {
                $flush($buffer);
                $buffer = [];
            }
            $visitor = $e->visitor_id;
            $buffer[] = ['name' => $e->name, 'experience' => $e->experience_handle, 'session' => $e->session_id, 'time' => $e->occurred_at->getTimestamp(), 'device' => $e->device, 'source' => $e->source];
        }
        if ($buffer) {
            $flush($buffer);
        }
        ksort($daily);

        $out = [];
        foreach ($steps as $i => $step) {
            $out[] = $step + [
                'label' => Events::label($step['event']),
                'visitors' => $reached[$i],
                'from_previous' => $i === 0 ? null : ($reached[$i - 1] ? $reached[$i] / $reached[$i - 1] * 100 : 0.0),
                'from_first' => $reached[0] ? $reached[$i] / $reached[0] * 100 : 0.0,
                'dropped' => $i === 0 ? 0 : $reached[$i - 1] - $reached[$i],
                'median_seconds' => $durations[$i] ? self::median($durations[$i]) : null,
            ];
        }
        arsort($segments);

        return ['steps' => $out, 'segments' => array_slice($segments, 0, 8, true), 'daily' => $daily, 'truncated' => $read >= self::MAX_EVENTS];
    }

    /**
     * The furthest a visitor got through the steps in order, within the window.
     *
     * @return array{0: int, 1: list<int>, 2: array} [steps reached, the time of each, the first step's event]
     */
    private function walk(array $events, array $steps, int $n, ?int $seconds, bool $sameSession): array
    {
        $best = 0;
        $bestTimes = [];
        $bestFirst = [];
        $k = 0;
        $times = [];
        $first = null;
        $matches = fn (array $e, array $step) => $e['name'] === $step['event'] && (empty($step['experience']) || $e['experience'] === $step['experience']);

        foreach ($events as $e) {
            if ($k > 0 && $k < $n && (($sameSession && $e['session'] !== $first['session']) || ($seconds !== null && $e['time'] - $first['time'] > $seconds))) {
                // Out of the window: keep what this attempt reached and start over.
                if ($k > $best) {
                    [$best, $bestTimes, $bestFirst] = [$k, $times, $first];
                }
                [$k, $times, $first] = [0, [], null];
            }
            if ($k < $n && $matches($e, $steps[$k])) {
                $times[$k] = $e['time'];
                if ($k === 0) {
                    $first = $e;
                }
                $k++;
            }
        }
        if ($k > $best) {
            [$best, $bestTimes, $bestFirst] = [$k, $times, $first];
        }

        return [$best, $bestTimes, $bestFirst ?? []];
    }

    private static function median(array $values): int
    {
        sort($values);
        $mid = intdiv(count($values), 2);

        return (int) (count($values) % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2);
    }

    /** "2 h 5 min" style durations. */
    public static function human(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds.' s';
        }
        if ($seconds < 3600) {
            return round($seconds / 60).' min';
        }
        if ($seconds < 86400) {
            return floor($seconds / 3600).' h '.round(($seconds % 3600) / 60).' min';
        }

        return round($seconds / 86400, 1).' days';
    }
}
