<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Shopify\Billing;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    public function __construct(private readonly Billing $billing) {}

    public function index(Store $store): View
    {
        $syncError = null;

        try {
            $this->billing->sync($store);
        } catch (Throwable $e) {
            report($e);
            $syncError = 'Your Shopify connection needs attention. Open Settings → Store to review it.';
        }

        return view('app.billing', [
            'store' => $store->fresh(),
            'subscription' => $store->activeSubscription()->first(),
            'plans' => config('shopify.billing.plans'),
            'syncError' => $syncError,
        ]);
    }

    public function subscribe(Request $request, Store $store): Response
    {
        $data = $request->validate(['plan' => 'required|in:'.implode(',', array_keys(config('shopify.billing.plans')))]);

        $confirmationUrl = $this->billing->createSubscription($store, $data['plan']);

        // The approval page must open in the top frame, outside the admin iframe.
        return response()->view('app.redirect', ['url' => $confirmationUrl]);
    }
}
