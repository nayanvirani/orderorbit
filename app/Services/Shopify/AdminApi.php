<?php

namespace App\Services\Shopify;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AdminApi
{
    public function __construct(private readonly TokenExchange $tokens) {}

    public function graphql(Store $store, string $query, array $variables = []): array
    {
        if ($store->needsTokenRefresh()) {
            $this->tokens->refresh($store);
        }

        $version = config('shopify.api_version');

        $response = Http::withHeaders(['X-Shopify-Access-Token' => $store->access_token])
            ->acceptJson()
            ->retry(3, 500, fn ($e, $request) => $e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 429, throw: false)
            ->post("https://{$store->shop_domain}/admin/api/{$version}/graphql.json", [
                'query' => $query,
                'variables' => (object) $variables,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Admin API request failed for {$store->shop_domain}: HTTP {$response->status()}");
        }

        $body = $response->json();

        if (! empty($body['errors'])) {
            throw new RuntimeException('Admin API errors: '.json_encode($body['errors']));
        }

        return $body['data'] ?? [];
    }
}
