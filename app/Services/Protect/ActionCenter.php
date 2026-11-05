<?php

namespace App\Services\Protect;

use App\Models\Profit\AdExpense;
use App\Models\Profit\ProfitOrder;
use App\Models\Protect\ProtectAlert;
use App\Models\Store;

/**
 * Action Center (G13): open alerts (issues) and suggestions in one list, sorted by money at risk,
 * each with Fix, Snooze and Done. Suggestions are found on the same hourly check as the alerts and
 * close themselves the same way once their cause is gone.
 */
class ActionCenter
{
    /** Per rule: [area, what the amount means]. */
    public const AREAS = [
        'price_mistake' => ['Pricing', 'less a week'], 'below_cost' => ['Profit', 'lost in 30 days'], 'negative_margin' => ['Profit', 'lost in 30 days'],
        'missing_cost' => ['Profit', 'sales with no profit'], 'low_stock' => ['Stock', 'weekly sales'], 'sold_out' => ['Stock', 'weekly sales'],
        'discount_margin' => ['Discounts', 'lost in 30 days'], 'refund_spike' => ['Refunds', 'more than usual'], 'loss_orders' => ['Profit', 'lost in 30 days'],
        'page_down' => ['Pages', null],
        'idea_costs' => ['Profit', 'sales with no profit'], 'idea_product_data' => ['Product data', 'sales on these products'],
        'idea_idle_stock' => ['Stock', 'tied up in stock'], 'idea_first_offer' => ['Offers', null], 'idea_ad_spend' => ['Profit', null],
    ];

    public function __construct(private readonly Protect $protect) {}

    /** The suggestions true right now (stored as alerts with severity "idea"). */
    public function ideas(Store $store): array
    {
        $currency = $store->currency ?: 'USD';
        $money = fn (float $v) => $currency.' '.number_format($v, 2);
        $variants = $this->protect->variants($store);
        $sales = $this->protect->sales($store);
        $out = [];

        // Products without a cost: their sales are left out of profit.
        $uncosted = $variants->filter(fn ($v) => $v->effective() === null);
        if ($store->planIncludes('profit') && ($n = $uncosted->pluck('product_id')->unique()->count())) {
            $at = round($uncosted->sum(fn ($v) => $sales[(int) $v->variant_id][1] ?? 0), 2);
            $out[] = ['rule' => 'idea_costs', 'subject' => 'store', 'title' => "Add costs to {$n} ".($n === 1 ? 'product' : 'products'),
                'what' => 'Their sales are left out of profit until they have a cost.', 'cost' => $at, 'cost_text' => $money($at).' of sales in 30 days without profit',
                'fix' => 'Add costs in Profit › Costs, import a CSV, or set "Cost per item" in Shopify.', 'cta' => 'Add costs', 'cta_url' => route('app.profit.costs', [], false)];
        }

        // Product data gaps (images, descriptions, SKUs…) on products that are for sale.
        if ($store->planIncludes('product_data')) {
            $gaps = array_filter($this->protect->productData($store), fn ($p) => array_diff(array_keys($p['issues']), ['cost']));
            if ($n = count($gaps)) {
                $at = round(array_sum(array_column($gaps, 'sales')), 2);
                $out[] = ['rule' => 'idea_product_data', 'subject' => 'store', 'title' => "Fill in missing details on {$n} ".($n === 1 ? 'product' : 'products'),
                    'what' => 'Missing images, descriptions, SKUs, barcodes or weights.', 'cost' => $at, 'cost_text' => $money($at).' of sales in 30 days on these products',
                    'fix' => 'Start with the best sellers at the top of Product data.', 'cta' => 'See product data', 'cta_url' => route('app.protect.products', [], false)];
            }
        }

        // Stock that hasn't sold in 30 days.
        if ($store->planIncludes('protect_alerts')) {
            $idle = array_filter($this->protect->inventory($store), fn ($r) => $r['status'] === 'no_sales' && $r['stock_value'] > 0);
            if ($n = count($idle)) {
                $value = round(array_sum(array_column($idle, 'stock_value')), 2);
                $out[] = ['rule' => 'idea_idle_stock', 'subject' => 'store', 'title' => "{$n} ".($n === 1 ? 'product hasn\'t' : 'products haven\'t').' sold in 30 days',
                    'what' => $money($value).' of stock at cost is sitting still.', 'cost' => $value, 'cost_text' => $money($value).' tied up in stock',
                    'fix' => 'Put them in a bundle with a best seller, or on sale.', 'cta' => 'See inventory', 'cta_url' => route('app.protect.inventory', ['status' => 'no_sales'], false)];
            }
        }

        if (! $store->experiences()->where('status', 'published')->exists()) {
            $out[] = ['rule' => 'idea_first_offer', 'subject' => 'store', 'title' => 'Publish your first offer',
                'what' => 'No Growvia offer is live on your store yet.', 'cost' => 0, 'cost_text' => 'No offer live',
                'fix' => 'Pick a bundle, gift bar or upsell and publish it.', 'cta' => 'Create an offer', 'cta_url' => route('app.cro.create', [], false)];
        }

        // Orders but no ad spend: profit after ads can't be worked out.
        if ($store->planIncludes('profit') && ProfitOrder::where('store_id', $store->id)->where('excluded', false)->where('day', '>=', now()->subDays(29)->toDateString())->exists()
            && ! AdExpense::where('store_id', $store->id)->exists()) {
            $out[] = ['rule' => 'idea_ad_spend', 'subject' => 'store', 'title' => 'Add your ad spend',
                'what' => 'Without it, profit stops at contribution and ROAS can\'t be shown.', 'cost' => 0, 'cost_text' => 'Profit after ads unknown',
                'fix' => 'Enter what you spend on ads, or import a CSV.', 'cta' => 'Add ad spend', 'cta_url' => route('app.profit.ads', [], false)];
        }

        return array_map(fn ($i) => $i + ['severity' => 'idea'], $out);
    }

    /** Open issues and suggestions: urgent first, then by money, then suggestions by money. */
    public function items(Store $store): array
    {
        return ProtectAlert::where('store_id', $store->id)->where('status', 'open')->get()
            ->sortBy(fn ($a) => [['urgent' => 0, 'warning' => 1, 'idea' => 2][$a->severity] ?? 3, -$a->cost])->values()
            ->map(fn (ProtectAlert $a) => [
                'id' => $a->id, 'kind' => $a->severity === 'idea' ? 'idea' : 'issue', 'severity' => $a->severity, 'rule' => $a->rule,
                'area' => self::AREAS[$a->rule][0] ?? 'Store', 'money' => $a->cost > 0 ? $a->cost : null, 'money_note' => self::AREAS[$a->rule][1] ?? null,
                'title' => $a->title, 'detail' => $a->what, 'fix' => $a->fix, 'cta' => $a->cta, 'cta_url' => $a->cta_url,
            ])->all();
    }
}
