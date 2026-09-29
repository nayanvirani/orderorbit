<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use App\Services\Shopify\StoreSync;
use App\Services\Shopify\TokenExchange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class StoreSettingsController extends Controller
{
    public function show(Store $store): View
    {
        return view('app.settings.store', [
            'store' => $store,
            'missingScopes' => $store->missingScopes(),
            'grantedScopes' => array_filter(explode(',', (string) $store->scopes)),
        ]);
    }

    public function recheck(Store $store, StoreSync $sync): RedirectResponse
    {
        try {
            $sync->sync($store);
        } catch (Throwable $e) {
            report($e);

            return redirect()->to(app_route('app.settings.store', ['notice' => 'shopify']));
        }

        AuditLog::record('store.capabilities_checked', $store);

        return redirect()->to(app_route('app.settings.store', ['notice' => 'rechecked']));
    }

    public function reconnect(Request $request, Store $store, TokenExchange $tokens, StoreSync $sync): RedirectResponse
    {
        try {
            $tokens->exchange($store, (string) $request->input('id_token'));
            $sync->sync($store);
        } catch (Throwable $e) {
            report($e);

            return redirect()->to(app_route('app.settings.store', ['notice' => 'shopify']));
        }

        AuditLog::record('store.reconnected', $store);

        return redirect()->to(app_route('app.settings.store', ['notice' => 'reconnected']));
    }
}
