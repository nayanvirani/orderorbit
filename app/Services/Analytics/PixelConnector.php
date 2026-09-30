<?php

namespace App\Services\Analytics;

use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Registers the OrderOrbit Space web pixel (extensions/orderorbit-pixel) on a store,
 * with a per-store token the pixel sends back so the collector knows the store.
 */
class PixelConnector
{
    public function __construct(private readonly AdminApi $api) {}

    public function connected(Store $store): bool
    {
        return (bool) $store->web_pixel_id;
    }

    /**
     * Connects if needed, at most one automatic attempt per hour (install, dashboard).
     */
    public function ensure(Store $store): void
    {
        if ($this->connected($store) || ! \Illuminate\Support\Facades\Cache::add('pixel-attempt:'.$store->id, true, 3600)) {
            return;
        }
        try {
            $this->connect($store);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function connect(Store $store): void
    {
        if (! $store->pixel_token) {
            $store->forceFill(['pixel_token' => Str::random(40)])->save();
        }
        $settings = json_encode(['token' => $store->pixel_token]);

        $created = $this->api->graphql($store, <<<'GQL'
            mutation ConnectPixel($settings: JSON!) {
              webPixelCreate(webPixel: { settings: $settings }) {
                webPixel { id }
                userErrors { field message code }
              }
            }
            GQL, ['settings' => $settings]);

        $id = $created['webPixelCreate']['webPixel']['id'] ?? null;
        if (! $id) {
            // Already registered (e.g. a reinstall): point it at this token.
            $existing = $this->api->graphql($store, '{ webPixel { id } }')['webPixel']['id'] ?? null;
            if (! $existing) {
                throw new RuntimeException('Analytics pixel not connected: '.($created['webPixelCreate']['userErrors'][0]['message'] ?? 'unknown error'));
            }
            $updated = $this->api->graphql($store, <<<'GQL'
                mutation UpdatePixel($id: ID!, $settings: JSON!) {
                  webPixelUpdate(id: $id, webPixel: { settings: $settings }) { webPixel { id } userErrors { field message } }
                }
                GQL, ['id' => $existing, 'settings' => $settings]);
            $id = $updated['webPixelUpdate']['webPixel']['id'] ?? $existing;
        }

        $store->forceFill(['web_pixel_id' => $id])->save();
    }
}
