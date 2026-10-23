<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $hmac = (string) $request->header('X-Shopify-Hmac-Sha256');
        $valid = $hmac !== '' && collect(\App\Services\Shopify\Secrets::verifying())
            ->contains(fn (string $secret) => hash_equals(base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)), $hmac));

        if (! $valid) {
            return response('Unauthorized', 401);
        }

        return $next($request);
    }
}
