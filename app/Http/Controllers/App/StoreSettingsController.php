<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use App\Services\Shopify\StoreSync;
use App\Services\Shopify\TokenExchange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Spa\Page;
use Throwable;

class StoreSettingsController extends Controller
{
    public function show(Store $store): Page
    {
        return page('settings/store', [
            'store' => [
                'name' => $store->name, 'shop_domain' => $store->shop_domain, 'shopify_plan' => $store->shopify_plan,
                'currency' => $store->currency, 'timezone' => $store->timezone, 'installed_at' => $store->installed_at,
                'installed' => $store->isInstalled(), 'theme_name' => $store->theme_name, 'pixel' => (bool) $store->web_pixel_id,
                'capabilities_checked_at' => $store->capabilities_checked_at,
            ],
            'capabilities' => collect(['presentment_currencies', 'online_store_2', 'checkout_blocks', 'development_store', 'thank_you_blocks', 'new_customer_accounts'])
                ->mapWithKeys(fn ($key) => [$key => $store->capability($key)])->all(),
            'missingScopes' => $store->missingScopes(),
            'grantedScopes' => array_values(array_filter(explode(',', (string) $store->scopes))),
            'themeEditorUrl' => $store->adminUrl('themes/current/editor'),
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
            $tokens->exchange($store, (string) ($request->bearerToken() ?? $request->input('id_token')));
            $sync->sync($store);
        } catch (Throwable $e) {
            report($e);

            return redirect()->to(app_route('app.settings.store', ['notice' => 'shopify']));
        }

        AuditLog::record('store.reconnected', $store);

        return redirect()->to(app_route('app.settings.store', ['notice' => 'reconnected']));
    }
}
