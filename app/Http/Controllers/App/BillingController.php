<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
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
    public function index(Request $request, Store $store, Billing $billing, Usage $usage): View
    {
        $syncError = false;

        try {
            $billing->sync($store);
        } catch (Throwable $e) {
            report($e);
            $syncError = true;
        }

        $store->refresh();

        return view('app.settings.billing', [
            'store' => $store,
            'subscription' => $store->activeSubscription()->first(),
            'latest' => $store->subscriptions()->latest('id')->first(),
            'plans' => config('shopify.billing.plans'),
            'usage' => $usage->summary($store),
            'syncError' => $syncError,
            'canManage' => $request->attributes->get('storeUser')?->can('manage_billing'),
        ]);
    }
}
