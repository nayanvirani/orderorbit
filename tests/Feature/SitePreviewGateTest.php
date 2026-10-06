<?php

namespace Tests\Feature;

use App\Http\Middleware\SitePreviewGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePreviewGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.preview_password' => 'orbit-test-pass']);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_visitors_only_see_coming_soon(): void
    {
        foreach (['/', '/features/bundles', '/pricing', '/templates'] as $url) {
            $this->get($url)->assertOk()->assertSee('coming soon')->assertDontSee('Install on Shopify')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noai, noimageai');
        }
        // Legal pages stay public (required for the Shopify listing); the app is never gated.
        $this->get('/privacy')->assertOk()->assertDontSee('Owner access');
        $this->get('/up')->assertOk();
    }

    public function test_owner_password_unlocks_the_site(): void
    {
        $this->post('/site-access', ['password' => 'wrong'])->assertForbidden()->assertSee('That password isn', false);

        $response = $this->post('/site-access', ['password' => 'orbit-test-pass'])->assertRedirect('/');
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === SitePreviewGate::COOKIE);
        $this->assertNotNull($cookie);

        $this->withCookie(SitePreviewGate::COOKIE, SitePreviewGate::token())->get('/')->assertOk()->assertDontSee('coming soon');
    }

    public function test_no_password_means_no_gate(): void
    {
        config(['site.preview_password' => null]);
        $this->get('/')->assertOk()->assertDontSee('Owner access');
    }
}
