<?php

namespace App\Services\Shopify;

use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Obtains offline access tokens via token exchange (Shopify managed installation).
 *
 * @see https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/token-exchange
 */
class TokenExchange
{
    public function exchange(Store $store, string $sessionToken): Store
    {
        $response = Http::asJson()->acceptJson()->post("https://{$store->shop_domain}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
            'subject_token' => $sessionToken,
            'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token',
            'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Token exchange failed for {$store->shop_domain}: HTTP {$response->status()}");
        }

        $firstInstall = $store->installed_at === null || $store->uninstalled_at !== null;

        $this->storeTokens($store, $response->json());
        $store->forceFill([
            'installed_at' => $firstInstall ? now() : $store->installed_at,
            'uninstalled_at' => null,
        ])->save();

        AuditLog::record($firstInstall ? 'store.installed' : 'store.token_refreshed', $store, ['scopes' => $store->scopes]);

        return $store;
    }

    public function refresh(Store $store): Store
    {
        if ($store->refresh_token === null) {
            return $store;
        }

        $response = Http::asJson()->acceptJson()->post("https://{$store->shop_domain}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $store->refresh_token,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Token refresh failed for {$store->shop_domain}: HTTP {$response->status()}");
        }

        $this->storeTokens($store, $response->json());
        $store->save();

        return $store;
    }

    private function storeTokens(Store $store, array $data): void
    {
        $store->forceFill([
            'access_token' => $data['access_token'],
            'scopes' => $data['scope'] ?? $store->scopes,
            'refresh_token' => $data['refresh_token'] ?? null,
            'access_token_expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
        ]);
    }
}
