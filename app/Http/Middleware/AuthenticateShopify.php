<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidSessionToken;
use App\Models\Store;
use App\Models\StoreUser;
use App\Services\Shopify\AdminApi;
use App\Services\Shopify\SessionToken;
use App\Services\Shopify\ShopDomain;
use App\Services\Shopify\TokenExchange;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Authenticates embedded-app requests with App Bridge session tokens.
 *
 * Document requests without a fresh token get a "bounce" page that asks App Bridge
 * for one and reloads; XHR/fetch requests get a 401 with Shopify's retry header.
 */
class AuthenticateShopify
{
    public function __construct(
        private readonly SessionToken $sessionTokens,
        private readonly TokenExchange $tokenExchange,
        private readonly AdminApi $api,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->input('id_token');

        try {
            $claims = $token ? $this->sessionTokens->decode($token) : null;
        } catch (InvalidSessionToken) {
            $claims = null;
        }

        if ($claims === null) {
            return $this->unauthenticated($request);
        }

        $shop = SessionToken::shopFromClaims($claims);
        $store = Store::firstOrNew(['shop_domain' => $shop]);

        if (! $store->exists || ! $store->isInstalled() || $this->scopesChanged($store)) {
            $store = $this->tokenExchange->exchange($store, $token);
            $this->syncShopDetails($store);
        }

        $user = null;
        if (isset($claims['sub'])) {
            $user = StoreUser::firstOrCreate(
                ['store_id' => $store->id, 'shopify_user_id' => (int) $claims['sub']],
                ['role' => $store->users()->exists() ? 'staff' : 'owner'],
            );
            $user->forceFill(['last_active_at' => now()])->save();
        }

        $request->attributes->set('store', $store);
        $request->attributes->set('storeUser', $user);
        app()->instance(Store::class, $store);

        $response = $next($request);
        $response->headers->set('Content-Security-Policy', "frame-ancestors https://{$shop} https://admin.shopify.com;");

        return $response;
    }

    private function unauthenticated(Request $request): Response
    {
        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            $shop = $request->query('shop');
            $ancestors = ShopDomain::isValid($shop) ? "https://{$shop}" : 'https://*.myshopify.com';

            // A token that still fails after one bounce will never succeed; stop the loop.
            $view = $request->query('bounced') ? 'app.session-error' : 'app.bounce';

            return response()->view($view, [], $view === 'app.bounce' ? 200 : 401)
                ->header('Content-Security-Policy', "frame-ancestors {$ancestors} https://admin.shopify.com;");
        }

        return response()->json(['message' => 'Your session has expired. Please reopen OrderOrbit from Shopify admin.'], 401)
            ->header('X-Shopify-Retry-Invalid-Session-Request', '1');
    }

    private function scopesChanged(Store $store): bool
    {
        $granted = array_filter(explode(',', (string) $store->scopes));
        $required = array_filter(explode(',', (string) config('shopify.scopes')));

        // write_x implies read_x, so only compare against scopes we have not been granted at all.
        foreach ($required as $scope) {
            $implied = str_replace('read_', 'write_', $scope);
            if (! in_array($scope, $granted, true) && ! in_array($implied, $granted, true)) {
                return true;
            }
        }

        return false;
    }

    private function syncShopDetails(Store $store): void
    {
        try {
            $shop = $this->api->graphql($store, '{ shop { name email currencyCode ianaTimezone plan { displayName } } }')['shop'];

            $store->forceFill([
                'name' => $shop['name'],
                'email' => $shop['email'],
                'currency' => $shop['currencyCode'],
                'timezone' => $shop['ianaTimezone'],
                'shopify_plan' => $shop['plan']['displayName'] ?? null,
            ])->save();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
