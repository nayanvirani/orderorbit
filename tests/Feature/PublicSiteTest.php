<?php

namespace Tests\Feature;

use App\Support\Content;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    public function test_public_pages_render(): void
    {
        $paths = ['/', '/how-it-works', '/features', '/solutions', '/templates', '/pricing', '/resources', '/blog', '/help', '/contact', '/about', '/security', '/privacy', '/terms', '/dpa', '/sitemap.xml'];
        $paths = array_merge(
            $paths,
            array_map(fn ($slug) => "/features/{$slug}", array_keys(Content::features())),
            array_map(fn ($slug) => "/solutions/{$slug}", array_keys(Content::solutions())),
        );

        foreach ($paths as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_merged_feature_pages_redirect(): void
    {
        $this->get('/features/free-gift')->assertRedirect('/features/progressive-gifts')->assertStatus(301);
        $this->get('/features/free-shipping-bar')->assertRedirect('/features/progressive-gifts');
        $this->get('/features/quantity-breaks')->assertRedirect('/features/bundles');
        $this->get('/features/upsell-cross-sell')->assertRedirect('/features/cart-upsells');
        $this->get('/features/progressive-gifts')->assertOk()->assertSee('Rewards that grow with the cart.');
        $this->get('/templates')->assertOk()->assertSee('A template is a ready-made layout')->assertSee('Quantity inversion offer')->assertSee('Radial counter');
    }

    public function test_unknown_feature_is_a_404_page(): void
    {
        $this->get('/features/not-a-feature')->assertNotFound()->assertSee('drifted');
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
