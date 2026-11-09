<?php

namespace App\Services\Questions;

use App\Models\Questions\StoreQuestion;
use Illuminate\Support\Facades\Cache;

/**
 * Store questions (G18): a library of ready questions, each a saved query over the store's own
 * data (no AI, no typing). Built from building blocks: measures × periods, comparisons, rankings
 * by product, variant, country, channel, day, weekday and order, plus stock, product data, offers
 * and alerts. Questions added in Internal Admin use the same blocks.
 */
class QuestionLibrary
{
    /** key => label (period words read naturally after the question: "… last month?"). */
    public const PERIODS = [
        'today' => 'today', 'yesterday' => 'yesterday', 'this_week' => 'this week', 'last_week' => 'last week',
        'last_7' => 'in the last 7 days', 'last_14' => 'in the last 14 days', 'this_month' => 'this month', 'last_month' => 'last month',
        'last_30' => 'in the last 30 days', 'last_90' => 'in the last 90 days', 'this_quarter' => 'this quarter', 'last_quarter' => 'last quarter',
        'this_year' => 'this year', 'last_year' => 'last year', 'last_12m' => 'in the last 12 months',
    ];

    /** key => [label, plural, kind (money | number | percent), topic, needs (costs | ads | null)] */
    public const MEASURES = [
        'net_sales' => ['net sales', true, 'money', 'Sales', null],
        'orders' => ['number of orders', false, 'number', 'Orders', null],
        'aov' => ['average order value', false, 'money', 'Orders', null],
        'units' => ['units sold', true, 'number', 'Sales', null],
        'gross' => ['sales before discounts', true, 'money', 'Sales', null],
        'discounts' => ['discounts given', true, 'money', 'Discounts and refunds', null],
        'refunded' => ['refunds', true, 'money', 'Discounts and refunds', null],
        'shipping' => ['shipping charged to customers', false, 'money', 'Costs', null],
        'shipping_cost' => ['own shipping cost', false, 'money', 'Costs', null],
        'fees' => ['payment fees', true, 'money', 'Costs', null],
        'cogs' => ['product cost', false, 'money', 'Costs', 'costs'],
        'gross_profit' => ['gross profit', false, 'money', 'Profit', 'costs'],
        'contribution' => ['contribution', false, 'money', 'Profit', 'costs'],
        'ad_spend' => ['ad spend', false, 'money', 'Costs', 'ads'],
        'after_ads' => ['profit after ads', false, 'money', 'Profit', 'costs'],
        'fixed_costs' => ['fixed costs', true, 'money', 'Costs', null],
        'estimated_net' => ['estimated net profit', false, 'money', 'Profit', 'costs'],
        'margin' => ['margin after ads', false, 'percent', 'Profit', 'costs'],
        'missing_cost_sales' => ['sales with no product cost', true, 'money', 'Costs', null],
        'new_customers' => ['new customers', true, 'number', 'Customers', null],
        'returning_share' => ['share of orders from returning customers', false, 'percent', 'Customers', null],
    ];

    /** Rankings: dimension => [plural noun, topic, measures [key => [question words, measure, direction]]]. */
    public const RANKINGS = [
        'product' => ['products', 'Products', [
            'top_sales' => ['Which products sold the most', 'net_sales', 'desc'], 'top_units' => ['Which products sold the most units', 'units', 'desc'],
            'top_profit' => ['Which products made the most profit', 'after_ads', 'desc'], 'low_margin' => ['Which products had the lowest margin', 'margin', 'asc'],
            'losing' => ['Which products lost the most money', 'after_ads', 'asc'], 'top_orders' => ['Which products were in the most orders', 'orders', 'desc'],
        ]],
        'variant' => ['variants', 'Products', [
            'top_sales' => ['Which variants sold the most', 'net_sales', 'desc'], 'top_units' => ['Which variants sold the most units', 'units', 'desc'],
            'top_profit' => ['Which variants made the most profit', 'after_ads', 'desc'], 'low_margin' => ['Which variants had the lowest margin', 'margin', 'asc'],
            'losing' => ['Which variants lost the most money', 'after_ads', 'asc'], 'top_orders' => ['Which variants were in the most orders', 'orders', 'desc'],
        ]],
        'country' => ['countries', 'Countries and channels', [
            'top_sales' => ['Which countries bought the most', 'net_sales', 'desc'], 'top_orders' => ['Which countries placed the most orders', 'orders', 'desc'],
            'top_profit' => ['Which countries made the most profit', 'after_ads', 'desc'], 'low_margin' => ['Which countries had the lowest margin', 'margin', 'asc'],
        ]],
        'channel' => ['sales channels', 'Countries and channels', [
            'top_sales' => ['Which sales channels sold the most', 'net_sales', 'desc'], 'top_orders' => ['Which sales channels brought the most orders', 'orders', 'desc'],
            'top_profit' => ['Which sales channels made the most profit', 'after_ads', 'desc'], 'low_margin' => ['Which sales channels had the lowest margin', 'margin', 'asc'],
        ]],
        'day' => ['days', 'Sales', [
            'top_sales' => ['Which day had the most sales', 'net_sales', 'desc'], 'low_sales' => ['Which day had the least sales', 'net_sales', 'asc'],
            'top_orders' => ['Which day had the most orders', 'orders', 'desc'],
        ]],
        'weekday' => ['weekdays', 'Sales', [
            'top_sales' => ['Which weekday sells the most', 'net_sales', 'desc'], 'top_orders' => ['Which weekday gets the most orders', 'orders', 'desc'],
        ]],
        'order' => ['orders', 'Orders', [
            'top_sales' => ['What were my biggest orders', 'net_sales', 'desc'], 'losing' => ['Which orders lost the most money', 'after_ads', 'asc'],
        ]],
    ];

    /** Questions that don't depend on a period: key => [topic, text]. */
    public const FIXED = [
        'stock_out_7' => ['Stock', 'Which products will sell out in the next 7 days?'],
        'stock_out_14' => ['Stock', 'Which products will sell out in the next 14 days?'],
        'stock_out_30' => ['Stock', 'Which products will sell out in the next 30 days?'],
        'stock_sold_out' => ['Stock', 'Which products that sell are sold out right now?'],
        'stock_reorder' => ['Stock', 'What should I reorder, and how much?'],
        'stock_idle' => ['Stock', 'Which products haven\'t sold in 30 days?'],
        'stock_value' => ['Stock', 'How much is my stock worth at cost?'],
        'stock_idle_value' => ['Stock', 'How much money is tied up in stock that isn\'t selling?'],
        'stock_year' => ['Stock', 'Which products have more than a year of stock?'],
        'data_cost' => ['Product data', 'Which products have no cost?'],
        'data_image' => ['Product data', 'Which products have no image?'],
        'data_description' => ['Product data', 'Which products have no description?'],
        'data_sku' => ['Product data', 'Which products have no SKU?'],
        'data_barcode' => ['Product data', 'Which products have no barcode?'],
        'data_weight' => ['Product data', 'Which products have no weight?'],
        'alerts_open' => ['Alerts', 'What problems are costing me money right now?'],
        'alerts_urgent' => ['Alerts', 'What needs fixing urgently?'],
        'health' => ['Alerts', 'What is my store\'s Health Score?'],
    ];

    /** The 10 questions the Free plan's answers are meant for: shown first. */
    public const STARTERS = ['value:net_sales:last_30', 'value:orders:last_30', 'value:aov:last_30', 'value:after_ads:last_month',
        'rank:product:top_sales:last_30', 'rank:product:top_profit:last_30', 'compare:net_sales:last_month', 'stock_out_14', 'data_cost', 'alerts_open'];

    /** @return array<string, array{id: string, topic: string, text: string, spec: array}> */
    public function all(): array
    {
        $built = Cache::remember('store-questions:library:v1', 86400, fn () => $this->build());
        foreach (StoreQuestion::where('active', true)->get() as $q) {
            $built['custom:'.$q->id] = ['id' => 'custom:'.$q->id, 'topic' => $q->topic, 'text' => $q->text, 'spec' => $q->spec];
        }

        return $built;
    }

    public function find(string $id): ?array
    {
        return $this->all()[$id] ?? null;
    }

    public function topics(): array
    {
        return ['Sales', 'Profit', 'Orders', 'Customers', 'Products', 'Countries and channels', 'Discounts and refunds', 'Costs', 'Stock', 'Product data', 'Growvia offers', 'Alerts'];
    }

    private function build(): array
    {
        $out = [];
        $add = function (string $id, string $topic, string $text, array $spec) use (&$out) {
            $out[$id] = ['id' => $id, 'topic' => $topic, 'text' => $text, 'spec' => $spec];
        };
        foreach (self::MEASURES as $m => [$label, $plural, , $topic]) {
            foreach (self::PERIODS as $p => $words) {
                $add("value:{$m}:{$p}", $topic, 'What '.($plural ? 'were' : 'was').' my '.$label.' '.$words.'?', ['kind' => 'value', 'measure' => $m, 'period' => $p]);
                $add("compare:{$m}:{$p}", $topic, 'How did my '.$label.' '.$words.' compare with the period before?', ['kind' => 'compare', 'measure' => $m, 'period' => $p]);
            }
        }
        foreach (self::RANKINGS as $dim => [, $topic, $measures]) {
            foreach ($measures as $key => [$words, $measure, $dir]) {
                foreach (self::PERIODS as $p => $period) {
                    $add("rank:{$dim}:{$key}:{$p}", $topic, "{$words} {$period}?", ['kind' => 'rank', 'dim' => $dim, 'measure' => $measure, 'dir' => $dir, 'period' => $p]);
                }
            }
        }
        foreach (self::PERIODS as $p => $words) {
            $add("offers:total:{$p}", 'Growvia offers', "How much did Growvia's offers add {$words}?", ['kind' => 'offers', 'mode' => 'total', 'period' => $p]);
            $add("offers:top:{$p}", 'Growvia offers', "Which Growvia offers earned the most {$words}?", ['kind' => 'offers', 'mode' => 'top', 'period' => $p]);
        }
        foreach (self::FIXED as $key => [$topic, $text]) {
            $add($key, $topic, $text, ['kind' => 'fixed', 'key' => $key]);
        }

        return $out;
    }
}
