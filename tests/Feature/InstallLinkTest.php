<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InstallLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_store_link_is_set_in_platform_settings(): void
    {
        config(['site.preview_password' => null]);
        $admin = User::forceCreate(['name' => 'Ava', 'email' => 'ava@growvia.test', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => 'super_admin']);
        $settings = ['warn_at' => 80, 'test_shops' => '', 'test_shop_plan' => array_key_first(config('shopify.billing.plans'))];

        // Before approval: "Install app" opens the contact page.
        $this->get('/')->assertSee('href="/contact" data-event="cta_install_clicked"', false);
        $this->actingAs($admin)->get('/admin/settings')->assertSee('the contact page')->assertSee('no App Store link is set yet');

        // After approval: every install button opens the listing.
        $this->actingAs($admin)->post('/admin/settings', $settings + ['install_url' => 'https://apps.shopify.com/growvia', 'sign_in_url' => ''])->assertRedirect();
        Cache::flush();
        PlatformSettings::boot();
        $this->get('/')->assertSee('href="https://apps.shopify.com/growvia" data-event="cta_install_clicked"', false)->assertSee('href="https://admin.shopify.com"', false);
        $this->get('/pricing')->assertSee('https://apps.shopify.com/growvia', false);

        // Clearing it goes back to the contact page; a bad address is refused.
        $this->actingAs($admin)->post('/admin/settings', $settings + ['install_url' => ''])->assertRedirect();
        config(['shopify.install_url' => '/contact']);
        Cache::flush();
        PlatformSettings::boot();
        $this->get('/')->assertSee('href="/contact" data-event="cta_install_clicked"', false);
        $this->actingAs($admin)->post('/admin/settings', $settings + ['install_url' => 'apps.shopify.com/growvia'])->assertSessionHasErrors('install_url');
    }
}
