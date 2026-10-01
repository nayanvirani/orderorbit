<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Billing\SalesMeter;
use App\Services\Shopify\Billing;
use App\Services\Usage;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Settings → Billing. Read-only under Shopify Managed Pricing: shows the plan
 * Shopify reports, usage against its limits, and links to Shopify's own plan
 * picker for changes.
 */
class BillingController extends Controller
{
    public function index(Request $request, Store $store, Billing $billing, SalesMeter $sales, Usage $usage): View
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

        return view('app.settings.billing', [
            'store' => $store,
            'subscription' => $store->activeSubscription()->first(),
            'latest' => $store->subscriptions()->latest('id')->first(),
            'plans' => config('shopify.billing.plans'),
            'sales' => $sales->status($store),
            // Live-offer limits the plan actually caps (Free: one of each revenue feature).
            'usage' => array_values(array_filter($usage->summary($store), fn ($m) => $m['limit'] !== null && in_array($m['meter'], ['bundles', 'free_gifts', 'cart_upsells', 'preorders'], true))),
            'cycles' => $store->salesCycles()->latest('starts_at')->limit(6)->get(),
            'syncError' => $syncError,
            'canManage' => $request->attributes->get('storeUser')?->can('manage_billing'),
        ]);
    }
}
