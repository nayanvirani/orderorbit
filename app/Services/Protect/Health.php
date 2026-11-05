<?php

namespace App\Services\Protect;

use App\Models\Protect\HealthScore;
use App\Models\Protect\MonitoredUrl;
use App\Models\Protect\ProtectAlert;
use App\Models\Store;

/**
 * Store Health Score (G12): 0–100 from profit setup, stock, product data, pages and open alerts.
 * A category that can't be measured yet (no costs synced, nothing tracked, no monitored pages) is
 * left out, not scored as 0. Saved once a day for the 8-week trend.
 */
class Health
{
    public const CATEGORIES = ['profit' => 'Profit setup', 'stock' => 'Stock', 'product_data' => 'Product data', 'pages' => 'Pages', 'alerts' => 'Open alerts'];

    public function __construct(private readonly Protect $protect) {}

    /**
     * [score (null when nothing is measured), categories: key => [value|null, note, count to fix]].
     */
    public function compute(Store $store): array
    {
        $variants = $this->protect->variants($store);
        $sales = $this->protect->sales($store);
        $cats = array_fill_keys(array_keys(self::CATEGORIES), [null, 'Not measured yet', 0]);

        if ($variants->isNotEmpty()) {
            // Profit setup: share of sales (or, with no sales yet, of variants) that have a cost.
            $total = $variants->sum(fn ($v) => max(0, $sales[(int) $v->variant_id][1] ?? 0));
            $uncosted = $variants->filter(fn ($v) => $v->effective() === null);
            $share = $total > 0 ? 1 - $uncosted->sum(fn ($v) => max(0, $sales[(int) $v->variant_id][1] ?? 0)) / $total : 1 - $uncosted->count() / $variants->count();
            $n = $uncosted->pluck('product_id')->unique()->count();
            $cats['profit'] = [(int) round($share * 100), $n ? "{$n} ".($n === 1 ? 'product has' : 'products have').' no cost' : 'Every product has a cost', $n];

            // Product data: products for sale without gaps (cost is counted in Profit setup).
            $products = $variants->pluck('product_id')->unique()->count();
            $gaps = count(array_filter($this->protect->productData($store), fn ($p) => array_diff(array_keys($p['issues']), ['cost'])));
            $cats['product_data'] = [(int) round((1 - $gaps / max(1, $products)) * 100), $gaps ? "{$gaps} of {$products} products with a gap" : 'No gaps found', $gaps];

            // Stock: tracked products that sell and aren't sold out or due for reorder.
            $selling = array_filter($this->protect->inventory($store), fn ($r) => $r['rate'] > 0);
            if ($selling) {
                $short = count(array_filter($selling, fn ($r) => in_array($r['status'], ['reorder', 'sold_out'], true)));
                $cats['stock'] = [(int) round((1 - $short / count($selling)) * 100), $short ? "{$short} to reorder or sold out" : 'Enough stock on everything that sells', $short];
            }
        }

        if ($store->planIncludes('url_monitor')) {
            $pages = MonitoredUrl::where('store_id', $store->id)->where('removed', false)->whereIn('status', ['up', 'slow', 'down'])->get();
            if ($pages->isNotEmpty()) {
                $down = $pages->where('status', 'down')->count();
                $slow = $pages->where('status', 'slow')->count();
                $value = ($pages->count() - $down - $slow * 0.25) / $pages->count();
                $cats['pages'] = [(int) round($value * 100), $down ? "{$down} ".($down === 1 ? 'page' : 'pages')." down of {$pages->count()}" : ($slow ? "{$slow} slow of {$pages->count()}" : "All {$pages->count()} pages up"), $down + $slow];
            }
        }

        $open = ProtectAlert::where('store_id', $store->id)->where('status', 'open')->where('severity', '!=', 'idea')->get();
        if ($variants->isNotEmpty() || $open->isNotEmpty()) {
            $urgent = $open->where('severity', 'urgent')->count();
            $warning = $open->count() - $urgent;
            $cats['alerts'] = [max(0, 100 - 15 * $urgent - 6 * $warning), $open->isEmpty() ? 'None open' : trim(($urgent ? "{$urgent} urgent" : '').($urgent && $warning ? ', ' : '').($warning ? "{$warning} ".($warning === 1 ? 'warning' : 'warnings') : '')), $open->count()];
        }

        $measured = array_filter($cats, fn ($c) => $c[0] !== null);

        return ['score' => $measured ? (int) round(array_sum(array_column($measured, 0)) / count($measured)) : null, 'categories' => $cats];
    }

    /** What would raise the score most: up to 3 categories, with the points each would add. */
    public function ups(array $health): array
    {
        $measured = array_filter($health['categories'], fn ($c) => $c[0] !== null);
        $ups = [];
        foreach ($measured as $key => [$value, $note, $n]) {
            if ($value >= 100 || ! $n) {
                continue;
            }
            $pts = (int) round((100 - $value) / count($measured));
            if ($pts < 1) {
                continue;
            }
            $ups[] = ['key' => $key, 'pts' => $pts] + match ($key) {
                'profit' => ['title' => "Add costs to {$n} ".($n === 1 ? 'product' : 'products'), 'detail' => 'So every order has a real profit number.', 'cta' => 'Add costs', 'href' => route('app.profit.costs', [], false)],
                'stock' => ['title' => "Reorder {$n} ".($n === 1 ? 'product' : 'products'), 'detail' => 'Products that sell are sold out or due for reorder.', 'cta' => 'See inventory', 'href' => route('app.protect.inventory', [], false)],
                'product_data' => ['title' => "Fill in details on {$n} ".($n === 1 ? 'product' : 'products'), 'detail' => 'Missing images, descriptions, SKUs, barcodes or weights.', 'cta' => 'See product data', 'href' => route('app.protect.products', [], false)],
                'pages' => ['title' => "Fix {$n} ".($n === 1 ? 'page' : 'pages'), 'detail' => 'Pages that are down or slow for shoppers.', 'cta' => 'See pages', 'href' => route('app.protect.urls', [], false)],
                'alerts' => ['title' => "Clear {$n} open ".($n === 1 ? 'alert' : 'alerts'), 'detail' => 'Fix the cause and the alert closes on the next check.', 'cta' => 'See Action Center', 'href' => route('app.protect.actions', [], false)],
            };
        }
        usort($ups, fn ($a, $b) => $b['pts'] <=> $a['pts']);

        return array_slice($ups, 0, 3);
    }

    /** Today's score, saved (one row a day, updated on each check). */
    public function record(Store $store): ?array
    {
        $health = $this->compute($store);
        if ($health['score'] !== null) {
            HealthScore::updateOrCreate(['store_id' => $store->id, 'day' => now($store->timezone ?: 'UTC')->toDateString()],
                ['score' => $health['score'], 'categories' => array_map(fn ($c) => $c[0], $health['categories'])]);
        }

        return $health;
    }

    /** The last 8 weeks (the last score of each week, oldest first) and the change since a week ago. */
    public function history(Store $store, ?int $now): array
    {
        $rows = HealthScore::where('store_id', $store->id)->where('day', '>=', now()->subWeeks(8)->toDateString())->orderBy('day')->get();
        $weeks = $rows->groupBy(fn ($r) => \Illuminate\Support\Carbon::parse($r->day)->startOfWeek()->toDateString())
            ->map(fn ($g, $week) => ['week' => $week, 'score' => $g->last()->score])->values()->all();
        $before = $rows->filter(fn ($r) => $r->day <= now()->subDays(7)->toDateString())->last();

        return ['weeks' => array_slice($weeks, -8), 'change' => $before && $now !== null ? $now - $before->score : null];
    }

    public static function label(?int $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score >= 85 => 'Great',
            $score >= 70 => 'Good',
            $score >= 50 => 'Fair',
            default => 'Needs work',
        };
    }
}
