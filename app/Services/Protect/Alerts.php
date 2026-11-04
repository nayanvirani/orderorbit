<?php

namespace App\Services\Protect;

use App\Models\Protect\ProtectAlert;
use App\Models\Store;
use App\Services\Mail\EmailSender;
use App\Services\Mail\OutgoingEmail;
use App\Support\StaffAccess;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Money-leak alerts (G7): problems that quietly cost money, each saying what happened, what it costs
 * and how to fix it. Checked every hour; an alert closes itself once the cause is gone, can be
 * snoozed for 7 days or muted, and is emailed at most once a day to the people who can see profit.
 */
class Alerts
{
    /** rule => [name, when, module that includes it] — Free has the three "protect" rules. */
    public const RULES = [
        'price_mistake' => ['Price mistake', 'Price drops to 0 or by over 50%', 'protect'],
        'low_stock' => ['Best seller low stock', 'Under 7 days of stock', 'protect'],
        'missing_cost' => ['Missing cost on a best seller', 'Top-20 product has no cost', 'protect'],
        'below_cost' => ['Price below cost', 'Selling price under unit cost', 'protect_alerts'],
        'negative_margin' => ['Margin turned negative', 'Contribution margin under 0', 'protect_alerts'],
        'sold_out' => ['Best seller sold out', 'Top-20 product at 0 available', 'protect_alerts'],
        'discount_margin' => ['Discount eats the margin', 'Discounted orders lose money', 'protect_alerts'],
        'refund_spike' => ['Refund spike', 'Over 2× the 30-day average', 'protect_alerts'],
        'loss_orders' => ['Loss-making orders spike', 'Over 10% of orders lose money', 'product_data'],
    ];

    public function __construct(private readonly Protect $protect, private readonly EmailSender $sender) {}

    /** Rules this store's plan includes and the merchant hasn't turned off. */
    public function activeRules(Store $store): array
    {
        $off = (array) (Protect::settings($store)['rules'] ?? []);

        return array_keys(array_filter(self::RULES, fn ($r, $key) => $store->planIncludes($r[2]) && ($off[$key] ?? true), ARRAY_FILTER_USE_BOTH));
    }

    /** Checks every rule, opens what's new, keeps what's still true and closes what's fixed. */
    public function check(Store $store): array
    {
        $rules = $this->activeRules($store);
        $found = $rules ? $this->find($store, $rules) : [];
        $now = now();
        $opened = [];
        $seen = [];
        foreach ($found as $a) {
            $seen[] = $a['rule'].'|'.$a['subject'];
            $alert = ProtectAlert::firstOrNew(['store_id' => $store->id, 'rule' => $a['rule'], 'subject' => $a['subject']]);
            $reopen = ! $alert->exists || $alert->status === 'closed';
            $alert->fill(array_diff_key($a, ['rule' => 1, 'subject' => 1]) + ['last_seen_at' => $now]);
            if ($reopen) {
                $alert->fill(['status' => 'open', 'opened_at' => $now, 'closed_at' => null, 'emailed_at' => null]);
            } elseif ($alert->status === 'snoozed' && $alert->snoozed_until?->isPast()) {
                $alert->status = 'open';
            }
            $alert->save();
            if ($reopen) {
                $opened[] = $alert;
            }
        }
        // Fixed: whatever is no longer found closes by itself (muted ones stay muted).
        $closed = ProtectAlert::where('store_id', $store->id)->whereIn('status', ['open', 'snoozed'])->get()
            ->reject(fn ($a) => in_array($a->rule.'|'.$a->subject, $seen, true))
            ->each(fn ($a) => $a->forceFill(['status' => 'closed', 'closed_at' => $now])->save())->count();

        $this->email($store);

        return ['opened' => count($opened), 'closed' => $closed, 'open' => ProtectAlert::where('store_id', $store->id)->where('status', 'open')->count()];
    }

    /** The alerts true right now, for the given rules. */
    public function find(Store $store, array $rules): array
    {
        $on = fn ($r) => in_array($r, $rules, true);
        $currency = $store->currency ?: 'USD';
        $money = fn (float $v) => $currency.' '.number_format($v, 2);
        $sales = $this->protect->sales($store);
        $best = $this->protect->bestSellers($sales);
        $variants = $this->protect->variants($store)->keyBy(fn ($v) => (int) $v->variant_id);
        $product = fn ($v) => $store->adminUrl('products/'.$v->product_id);
        $out = [];

        foreach ($variants as $id => $v) {
            $units = $sales[$id][0] ?? 0;
            $rate = $units / 30;
            $cost = $v->effective();
            if ($on('price_mistake') && $v->price_changed_at && $v->price_changed_at->gt(now()->subDays(7)) && $v->previous_price > 0 && ($v->price <= 0 || $v->price <= $v->previous_price * 0.5)) {
                $out[] = ['rule' => 'price_mistake', 'subject' => "v{$id}", 'severity' => 'urgent', 'title' => "{$v->title} price dropped to {$money((float) $v->price)}",
                    'what' => "It was {$money($v->previous_price)} until ".$v->price_changed_at->diffForHumans().'.', 'cost' => round(max(0, $v->previous_price - $v->price) * max(1, $rate * 7), 2),
                    'cost_text' => 'About '.$money(max(0, $v->previous_price - $v->price)).' less on every sale', 'fix' => 'Check the price in Shopify. If it\'s a sale, nothing to do.', 'cta' => 'Open product in Shopify', 'cta_url' => $product($v)];
            }
            if ($on('below_cost') && $cost !== null && $v->price > 0 && $v->price < $cost) {
                $out[] = ['rule' => 'below_cost', 'subject' => "v{$id}", 'severity' => 'urgent', 'title' => "{$v->title} price is below its cost",
                    'what' => "Price {$money($v->price)}, unit cost {$money($cost)}.", 'cost' => round(($cost - $v->price) * max(1, $units), 2),
                    'cost_text' => 'About '.$money($cost - $v->price).' lost per sale'.($units ? ', '.$money(($cost - $v->price) * $units).' in 30 days' : ''), 'fix' => 'Raise the price, or change the cost if it\'s wrong.', 'cta' => 'Open product in Shopify', 'cta_url' => $product($v)];
            }
        }

        foreach ($best as $id) {
            $v = $variants[$id] ?? null;
            if (! $v) {
                continue;
            }
            $rate = ($sales[$id][0] ?? 0) / 30;
            $weekly = round(($sales[$id][1] ?? 0) / 30 * 7, 2);
            if ($on('low_stock') && $v->tracked && $v->available > 0 && $rate > 0 && $v->available / $rate < 7) {
                $days = (int) floor($v->available / $rate);
                $out[] = ['rule' => 'low_stock', 'subject' => "v{$id}", 'severity' => 'urgent', 'title' => "Best seller running low: {$v->title}",
                    'what' => "{$v->available} left, selling ".round($rate, 1).' a day: about '.$days.' '.($days === 1 ? 'day' : 'days').' of stock.', 'cost' => $weekly,
                    'cost_text' => 'About '.$money($weekly).' of weekly sales at risk', 'fix' => 'Reorder by '.now()->addDays(max(0, $days - 1))->format('D j M').'.', 'cta' => 'See inventory', 'cta_url' => route('app.protect.inventory', [], false)];
            }
            if ($on('sold_out') && $v->tracked && (int) $v->available <= 0) {
                $out[] = ['rule' => 'sold_out', 'subject' => "v{$id}", 'severity' => 'urgent', 'title' => "Best seller sold out: {$v->title}",
                    'what' => 'It sold '.round($rate, 1).' a day over the last 30 days and has none available.', 'cost' => $weekly,
                    'cost_text' => 'About '.$money($weekly).' of sales a week', 'fix' => 'Restock it, or take pre-orders while stock is on its way.', 'cta' => 'See inventory', 'cta_url' => route('app.protect.inventory', [], false)];
            }
            if ($on('missing_cost') && $v->effective() === null) {
                $out[] = ['rule' => 'missing_cost', 'subject' => "v{$id}", 'severity' => 'warning', 'title' => "Missing cost on a best seller: {$v->title}",
                    'what' => 'It\'s in your top 20 by sales but has no cost, so its profit is left out.', 'cost' => $sales[$id][1] ?? 0,
                    'cost_text' => $money($sales[$id][1] ?? 0).' of sales in 30 days without profit', 'fix' => 'Add its cost in Profit › Costs, or set "Cost per item" in Shopify.', 'cta' => 'Add the cost', 'cta_url' => route('app.profit.costs', [], false)];
            }
        }

        if ($on('negative_margin')) {
            [$from, $to] = \App\Services\Profit\ProfitReports::range($store, '30');
            foreach ($this->protect->reports()->breakdown($store, $from, $to, 'product') as $p) {
                if (! $p['missing_cost'] && $p['net_sales'] > 0 && $p['contribution'] < 0) {
                    $out[] = ['rule' => 'negative_margin', 'subject' => $p['key'], 'severity' => 'warning', 'title' => "{$p['name']} loses money on each sale",
                        'what' => "Contribution margin {$p['contribution_margin']}% over the last 30 days ({$p['orders']} orders).", 'cost' => abs($p['contribution']),
                        'cost_text' => $money(abs($p['contribution'])).' lost in 30 days', 'fix' => 'Check its price, cost and discounts, or the shipping and fees on its orders.', 'cta' => 'See breakdown', 'cta_url' => route('app.profit.breakdowns', ['by' => 'product'], false)];
                }
            }
        }

        $orders = ($on('discount_margin') || $on('loss_orders')) ? $this->protect->orderContributions($store) : collect();
        if ($on('discount_margin')) {
            foreach ($orders->filter(fn ($o) => $o->costed && $o->codes)->flatMap(fn ($o) => array_map(fn ($c) => [$c, $o], $o->codes))->groupBy(fn ($x) => $x[0]) as $code => $rows) {
                $losing = collect($rows)->filter(fn ($x) => $x[1]->contribution < 0);
                $loss = round(-$losing->sum(fn ($x) => $x[1]->contribution), 2);
                if ($losing->count() >= 3 && $loss > 0) {
                    $out[] = ['rule' => 'discount_margin', 'subject' => 'code:'.mb_substr($code, 0, 100), 'severity' => 'warning', 'title' => "Discount code {$code} eats the margin",
                        'what' => "{$losing->count()} of ".count($rows).' orders with '.$code.' lost money in the last 30 days.', 'cost' => $loss,
                        'cost_text' => $money($loss).' lost on those orders', 'fix' => 'Lower the discount or leave low-margin products out of it.', 'cta' => 'Edit discount in Shopify', 'cta_url' => $store->adminUrl('discounts')];
                }
            }
        }
        if ($on('loss_orders')) {
            $costed = $orders->filter(fn ($o) => $o->costed);
            $losing = $costed->filter(fn ($o) => $o->contribution < 0);
            if ($costed->count() >= 20 && $losing->count() / $costed->count() > 0.10) {
                $out[] = ['rule' => 'loss_orders', 'subject' => 'store', 'severity' => 'warning', 'title' => 'More orders are losing money',
                    'what' => round($losing->count() / $costed->count() * 100).'% of orders in the last 30 days had negative contribution.', 'cost' => round(-$losing->sum('contribution'), 2),
                    'cost_text' => $money(-$losing->sum('contribution')).' lost on '.$losing->count().' orders', 'fix' => 'Open the order breakdown to see what they have in common: discounts, shipping or one product.', 'cta' => 'See orders', 'cta_url' => route('app.profit.breakdowns', ['by' => 'order'], false)];
            }
        }
        if ($on('refund_spike')) {
            $week = \App\Models\Profit\ProfitDaily::where('store_id', $store->id)->where('day', '>=', now()->subDays(6)->toDateString())->sum('refunded');
            $month = \App\Models\Profit\ProfitDaily::where('store_id', $store->id)->whereBetween('day', [now()->subDays(36)->toDateString(), now()->subDays(7)->toDateString()])->sum('refunded');
            $usual = $month / 30 * 7;
            if ($week > 50 && $week > 2 * $usual) {
                $out[] = ['rule' => 'refund_spike', 'subject' => 'store', 'severity' => 'warning', 'title' => 'Refunds are up',
                    'what' => $money($week).' refunded in the last 7 days; usual is about '.$money($usual).'.', 'cost' => round($week - $usual, 2),
                    'cost_text' => $money($week - $usual).' more than usual this week', 'fix' => 'Check the refund reasons in Shopify for a product or a delivery problem.', 'cta' => 'See refunds', 'cta_url' => $store->adminUrl('orders?financial_status=refunded,partially_refunded')];
            }
        }

        return $out;
    }

    /** New open alerts, at most once a day per alert, to the people who can see profit. */
    private function email(Store $store): void
    {
        $due = ProtectAlert::where('store_id', $store->id)->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('emailed_at')->orWhere('emailed_at', '<', now()->subDay()))->whereNull('closed_at')
            ->where('opened_at', '>', now()->subDays(2))->orderByDesc('cost')->get();
        if ($due->isEmpty()) {
            return;
        }
        $people = $store->users()->whereNull('disabled_at')->whereNotNull('email')->get()->filter(fn ($u) => StaffAccess::can($u, 'profit'));
        $app = $store->adminUrl('apps/'.(Cache::get('shopify.app_handle') ?: config('shopify.app_handle')).'/app/protect/alerts');
        foreach ($people as $u) {
            try {
                $this->sender->send(OutgoingEmail::designed($u->email, ($due->count() === 1 ? $due->first()->title : $due->count().' problems found').' · '.($store->name ?: $store->shop_domain), [
                    'heading' => $due->count() === 1 ? $due->first()->title : 'Growvia found '.$due->count().' problems that cost money',
                    'intro' => 'Hi'.($u->first_name ? " {$u->first_name}" : '').",\n\n".$due->take(5)->map(fn ($a) => "• {$a->title}: {$a->cost_text}. {$a->fix}")->implode("\n"),
                    'cta' => ['See alerts', $app], 'footer' => 'Each alert is emailed at most once a day and closes itself once it\'s fixed. People who can see profit get these.',
                ], ['category' => 'alerts', 'storeId' => $store->id]));
            } catch (Throwable $e) {
                report($e);
            }
        }
        ProtectAlert::whereIn('id', $due->pluck('id'))->update(['emailed_at' => now()]);
    }
}
