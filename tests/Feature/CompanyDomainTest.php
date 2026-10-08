<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://growvia.orderorbit.space', 'site.company_host' => 'orderorbit.space', 'site.preview_password' => null]);
    }

    public function test_the_main_domain_shows_coming_soon_and_sends_old_links_to_growvia(): void
    {
        $this->get('https://orderorbit.space/')->assertOk()->assertSee('Coming soon')->assertSee('OrderOrbit Space')->assertSee('https://growvia.orderorbit.space', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://www.orderorbit.space/')->assertOk()->assertSee('Coming soon');
        $this->get('https://orderorbit.space/pricing?ref=x')->assertStatus(301)->assertRedirect('https://growvia.orderorbit.space/pricing?ref=x');
        $this->get('https://orderorbit.space/privacy')->assertStatus(301)->assertRedirect('https://growvia.orderorbit.space/privacy');
        $this->post('https://orderorbit.space/contact')->assertStatus(307);
    }

    public function test_growvia_keeps_its_website_and_the_main_domain_keeps_serving_the_app(): void
    {
        $this->get('https://growvia.orderorbit.space/')->assertOk()->assertDontSee('We build technology for online commerce');
        // Shopify's old URLs (webhooks, app, robots) still work on the main domain during the move.
        $this->get('https://orderorbit.space/robots.txt')->assertOk();
        $this->post('https://orderorbit.space/webhooks/shopify')->assertStatus(401);
    }

    public function test_nothing_changes_while_the_app_still_lives_on_the_main_domain(): void
    {
        config(['app.url' => 'https://orderorbit.space']);
        $this->get('https://orderorbit.space/')->assertOk()->assertDontSee('We build technology for online commerce');
    }

    public function test_saved_text_is_renamed_but_emails_and_code_names_stay(): void
    {
        $migration = require database_path('migrations/2026_10_13_000001_rename_to_growvia.php');
        $this->assertSame(
            'Growvia helps. Try a Growvia widget at growvia.orderorbit.space/docs. Mail support@orderorbit.space. window.OrderOrbitHooks and OrderOrbit.render stay.',
            $migration::rename('OrderOrbit Space helps. Try an OrderOrbit widget at orderorbit.space/docs. Mail support@orderorbit.space. window.OrderOrbitHooks and OrderOrbit.render stay.'),
        );
    }
}
