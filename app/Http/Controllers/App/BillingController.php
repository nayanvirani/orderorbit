<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Shopify\Billing;
use App\Services\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    public function __construct(private readonly Billing $billing) {}

    public function index(Request $request, Store $store, Usage $usage): View
    {
        $syncError = false;

        try {
            $this->billing->sync($store);
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

    public function subscribe(Request $request, Store $store): Response|RedirectResponse
    {
        $plan = (string) $request->input('plan');

        if (! array_key_exists($plan, config('shopify.billing.plans'))) {
            return redirect()->to(app_route('app.settings.billing', ['notice' => 'unexpected']));
        }

        try {
            $confirmationUrl = $this->billing->createSubscription($store, $plan);
        } catch (Throwable $e) {
            report($e);

            return redirect()->to(app_route('app.settings.billing', ['notice' => 'shopify']));
        }

        // The approval page must open in the top frame, outside the admin iframe.
        return response()->view('app.redirect', ['url' => $confirmationUrl]);
    }
}
