<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Website design (super admin): every colour, font and radius of the public website is a setting,
 * printed as CSS variables on every public page, the coming-soon page and the error pages.
 */
class WebsiteDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Google Fonts knows Inter (every weight) and "Bitcount" (only its default style).
        Http::fake(function (Request $request) {
            $url = urldecode($request->url());

            return match (true) {
                str_contains($url, 'family=Inter') => Http::response('@font-face{}'),
                str_contains($url, 'family=Bitcount&') => Http::response('@font-face{}'),
                default => Http::response('', 400),
            };
        });
    }

    private function admin(string $role = 'super_admin'): User
    {
        return User::forceCreate(['name' => 'Ava', 'email' => $role.'@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => $role]);
    }

    public function test_the_built_in_look_is_the_default(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('--c-bg: #faf9fe', false)
            ->assertSee('--c-accent: #5b45f0', false)
            ->assertSee('--f-heading: "Roboto"', false)
            ->assertSee('family=Roboto:ital,wght@0,300', false);
    }

    public function test_a_saved_design_reaches_every_public_page(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/website')->assertOk()->assertSee('Website design')->assertSee('Dark sections');

        $this->actingAs($admin)->post('/admin/website', ['theme' => [
            'bg' => '#FFF8EF', 'heading' => '#7a1f00', 'accent' => '#e4572e', 'dark_bg' => '#1f3b2d',
            'font_heading' => 'Inter', 'heading_weight' => '700', 'button_radius' => '6', 'body_size' => '17',
        ]])->assertRedirect()->assertSessionHas('status', 'Website design saved. It\'s live on every public page.');

        foreach (['/', '/pricing', '/features', '/this-page-does-not-exist'] as $url) {
            $this->get($url)
                ->assertSee('--c-bg: #fff8ef', false)
                ->assertSee('--c-heading: #7a1f00', false)
                ->assertSee('--c-dark-bg: #1f3b2d', false)
                ->assertSee('--f-heading: "Inter"', false)
                ->assertSee('--fw-heading: 700', false)
                ->assertSee('--radius-btn: 6px', false)
                ->assertSee('--fs-body: 17px', false)
                ->assertSee('family=Inter:ital,wght@', false);
        }
        // Untouched settings keep their defaults.
        $this->assertSame('#15123b', SiteTheme::get()['text']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.website_design_saved']);
    }

    public function test_invalid_values_are_rejected_and_keep_their_current_value(): void
    {
        $this->actingAs($this->admin())->post('/admin/website', ['theme' => [
            'bg' => 'red', 'text' => '#12345', 'font_body' => 'Not A Real Font', 'font_label' => 'Bitcount',
            'body_size' => '40', 'radius' => '12', 'heading_weight' => '950',
        ]])->assertSessionHas('status', fn ($s) => str_contains($s, 'Page → Page background') && str_contains($s, 'Page → Body text')
            && str_contains($s, 'Fonts → Body text and buttons') && str_contains($s, 'Fonts → Body text size') && str_contains($s, 'Fonts → Heading weight'));

        $theme = SiteTheme::get();
        $this->assertSame(['#faf9fe', '#15123b', 'Roboto', '16', '700'], [$theme['bg'], $theme['text'], $theme['font_body'], $theme['body_size'], $theme['heading_weight']]);
        $this->assertSame(['12', 'Bitcount'], [$theme['radius'], $theme['font_label']], 'Valid values in the same save are kept.');
        // A family without the extra weights is requested the way Google Fonts offers it.
        $this->get('/')->assertSee('family=Bitcount&', false);
    }

    public function test_reset_returns_to_the_built_in_look(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/website', ['theme' => ['accent' => '#e4572e']]);
        $this->get('/')->assertSee('--c-accent: #e4572e', false);

        $this->actingAs($admin)->post('/admin/website/reset')->assertRedirect();
        $this->get('/')->assertSee('--c-accent: #5b45f0', false);
    }

    public function test_only_super_admins_can_change_the_design(): void
    {
        $support = $this->admin('support');
        $this->actingAs($support)->get('/admin/website')->assertForbidden();
        $this->actingAs($support)->post('/admin/website', ['theme' => ['accent' => '#e4572e']])->assertForbidden();
        $this->assertSame('#5b45f0', SiteTheme::get()['accent']);
    }
}
