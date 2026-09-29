<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    public function test_public_pages_render(): void
    {
        foreach (['/', '/pricing', '/privacy', '/terms'] as $path) {
            $this->get($path)->assertOk()->assertSee('OrderOrbit');
        }
    }

    public function test_embedded_app_bounces_without_session_token(): void
    {
        $this->get('/app?shop=demo.myshopify.com&host=abc')
            ->assertOk()
            ->assertSee('shopify.idToken()', false)
            ->assertHeader('Content-Security-Policy', 'frame-ancestors https://demo.myshopify.com https://admin.shopify.com;');
    }

    public function test_embedded_api_calls_without_token_get_retry_header(): void
    {
        $this->getJson('/app')
            ->assertUnauthorized()
            ->assertHeader('X-Shopify-Retry-Invalid-Session-Request', '1');
    }
}
