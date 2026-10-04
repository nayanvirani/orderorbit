<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Billing\SalesMeter;
use App\Services\Shopify\Billing;
use App\Services\Usage;
use Illuminate\Http\Request;
use App\Support\Spa\Page;
use Throwable;

/**
 * Settings → Billing. Read-only under Shopify Managed Pricing: shows the plan
 * Shopify reports, usage against its limits, and links to Shopify's own plan
 * picker for changes.
 */
class BillingController extends Controller
{
    public function index(Request $request, Store $store, Billing $billing, SalesMeter $sales, Usage $usage): Page
    {
        $syncError = false;

        try {
            $billing->sync($store);
        } catch (Throwable $e) {
            report($e);
            $syncError = true;
        }

        // Show an up-to-date sales count against the plan's limit.
        $sales->refreshIfStale($store, 10);
        $store->refresh();

        $subscription = $store->activeSubscription()->first();
        $latest = $store->subscriptions()->latest('id')->first();
        $status = $sales->status($store);

        return page('settings/billing', [
            'effective' => $store->effectivePlan(),
            'plan' => $store->plan,
            'planExpiresAt' => $store->plan_expires_at?->isFuture() ? $store->plan_expires_at : null,
            'testShop' => $store->isTestShop(),
            'subscription' => $subscription && $store->plan ? [
                'plan' => $subscription->plan, 'price' => (float) $subscription->price, 'test' => (bool) $subscription->test,
                'trial_ends_at' => $subscription->trial_ends_at?->isFuture() ? $subscription->trial_ends_at : null,
                'current_period_ends_at' => $subscription->current_period_ends_at,
            ] : null,
            'latestStatus' => $latest?->status,
            'plans' => collect(config('shopify.billing.plans'))->map(fn ($p, $key) => ['key' => $key, 'name' => $p['name'], 'price' => (float) $p['price'], 'features' => $p['features']])->values(),
            'sales' => ['state' => $status['state'], 'sales' => $status['sales'], 'limit' => $status['limit'], 'percent' => $status['percent'],
                'deadline' => $status['deadline'], 'next' => $status['next'] ? ['name' => $status['next']['name'], 'price' => (float) $status['next']['price']] : null,
                'orders' => $status['orders'] ?? 0, 'test_orders' => $status['test_orders'] ?? null,
                'cycle_start' => $status['cycle_start'], 'cycle_end' => $status['cycle_end']],
            'graceDays' => (int) config('shopify.billing.grace_days'),
            // Live-offer limits the plan actually caps (Free: one of each revenue feature).
            'usage' => array_values(array_filter($usage->summary($store), fn ($m) => $m['limit'] !== null && in_array($m['meter'], ['bundles', 'free_gifts', 'cart_upsells', 'preorders'], true))),
            'cycles' => $store->salesCycles()->latest('starts_at')->limit(6)->get()->map(fn ($c) => [
                'id' => $c->id, 'starts_at' => $c->starts_at, 'ends_at' => $c->ends_at, 'orders' => (int) $c->orders_count,
                'sales' => (float) $c->sales_usd, 'plan' => config('shopify.billing.plans.'.$c->plan.'.name', ucfirst((string) $c->plan)),
                'limit' => $c->sales_limit, 'over' => (bool) $c->over_limit,
            ]),
            'syncError' => $syncError,
            // Only people who may change the plan get Shopify's plan picker.
            'pricingUrl' => $request->attributes->get('storeUser')?->can('manage_billing') ? $store->pricingUrl() : null,
            'billingUrl' => $store->adminUrl('settings/billing'),
        ]);
    }
}
