<?php

namespace Tests\Feature;

use App\Models\CroTemplate;
use App\Models\FeatureFlag;
use App\Models\Store;
use App\Models\Support\Ticket;
use App\Models\User;
use App\Experiences\Registry;
use App\Services\Experiences\TemplateLibrary;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class SupportAndAdminTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(fn (Request $r) => Http::response(['data' => []]));
        $this->store = $this->installedStore(['plan' => 'growth']);
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Ava Team', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true]);
    }

    public function test_merchants_open_tickets_and_follow_the_conversation(): void
    {
        $owner = $this->member($this->store, 'owner');
        $this->get('/app/support', $this->as($owner))->assertOk()->assertSee('Open a ticket')->assertSee('No tickets yet.');
        $this->post('/app/support', ['subject' => '', 'body' => 'x', 'category' => 'setup'], $this->as($owner))->assertOk()->assertSee('The subject field is required.');

        $this->post('/app/support', [
            'subject' => 'Bundle not showing', 'body' => 'The bundle doesn\'t appear on the Serum page.', 'category' => 'publishing', 'priority' => 'high',
            'attachments' => [UploadedFile::fake()->image('screen.png', 400, 300)],
        ], $this->as($owner))->assertRedirectContains('ticket_created');
        $ticket = Ticket::sole();
        $this->assertSame(['open', 'high', 'publishing', $this->store->shop_domain], [$ticket->status, $ticket->priority, $ticket->category, $ticket->diagnostics['shop']]);
        $this->assertSame(1, $ticket->messages()->first()->attachments()->count());

        // The team replies, adds an internal note, and the merchant only sees the reply.
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/tickets/'.$ticket->id.'/reply', ['body' => 'Is the app embed on?'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/tickets/'.$ticket->id.'/reply', ['body' => 'Their theme is old.', 'internal' => '1'])->assertRedirect();
        $this->assertSame('pending', $ticket->fresh()->status);
        $this->get('/app/support/'.$ticket->id, $this->as($owner))->assertOk()->assertSee('Is the app embed on?')->assertDontSee('Their theme is old.')->assertSee('screen.png');

        $attachment = $ticket->messages()->first()->attachments()->first();
        $this->get('/app/support/attachments/'.$attachment->id, $this->as($owner))->assertOk()->assertHeader('content-type', 'image/png');

        $this->post('/app/support/'.$ticket->id.'/reply', ['body' => 'Yes, it is on.'], $this->as($owner))->assertRedirectContains('reply_sent');
        $this->assertSame(['open', 'merchant'], [$ticket->fresh()->status, $ticket->fresh()->last_reply_by]);

        // Another store can't see it.
        $other = $this->installedStore(['shop_domain' => 'other.myshopify.com']);
        $this->assertNull(Ticket::where('store_id', $other->id)->first());
    }

    public function test_admin_requires_a_team_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('For the OrderOrbit team only.');
        User::forceCreate(['name' => 'Merchant', 'email' => 'm@example.com', 'password' => 'password-123', 'is_admin' => false]);
        $this->post('/admin/login', ['email' => 'm@example.com', 'password' => 'password-123'])->assertSessionHasErrors('email');
        $this->admin();
        $this->post('/admin/login', ['email' => 'ava@orderorbit.space', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => 'ava@orderorbit.space', 'password' => 'secret-password-123'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Installed stores')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->artisan('orderorbit:admin-user', ['email' => 'new@orderorbit.space'])->expectsOutputToContain('Password (shown once)')->assertSuccessful();
        $this->assertTrue(User::where('email', 'new@orderorbit.space')->value('is_admin'));
    }

    public function test_admin_pages_templates_and_flags(): void
    {
        app(TemplateLibrary::class)->sync();
        $admin = $this->admin();
        foreach (['/admin', '/admin/stores', '/admin/stores/'.$this->store->id, '/admin/failures', '/admin/analytics', '/admin/templates', '/admin/flags', '/admin/tickets', '/admin/audit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get('/admin/stores?q=demo')->assertSee($this->store->shop_domain);

        // Unpublishing a template hides it from new experiences only.
        $template = CroTemplate::where('type', 'countdown')->where('key', 'banner')->sole();
        $this->actingAs($admin)->post('/admin/templates/'.$template->id.'/toggle')->assertRedirect();
        $this->assertSame('unpublished', $template->fresh()->status);
        $this->assertArrayNotHasKey('banner', Registry::offered('countdown'));
        $this->assertNotNull(Registry::template('countdown', 'banner'), 'Existing experiences still find it.');
        app(TemplateLibrary::class)->sync();
        $this->assertSame('unpublished', $template->fresh()->status, 'Deploys keep the admin\'s choice.');

        // Feature flags: everyone, or only listed stores.
        $this->actingAs($admin)->post('/admin/flags', ['key' => 'email_sending', 'description' => 'Send workflow emails', 'store_domains' => $this->store->shop_domain])->assertRedirect();
        $this->assertTrue(Features::enabled('email_sending', $this->store));
        $this->assertFalse(Features::enabled('email_sending', $this->installedStore(['shop_domain' => 'b.myshopify.com'])));
        $this->assertFalse(Features::enabled('missing_flag', $this->store));
        $this->actingAs($admin)->post('/admin/flags/'.FeatureFlag::sole()->id.'/delete')->assertRedirect();
        $this->assertFalse(Features::enabled('email_sending', $this->store));
    }

    public function test_documentation_hub_and_guides_are_public(): void
    {
        config(['site.preview_password' => 'secret']);
        $this->get('/docs')->assertOk()->assertSee('Everything you need to')->assertSee('Lifecycle automation')->assertSee('Developers: theme callbacks');
        foreach (\App\Http\Controllers\SiteController::DOCS as $slug) {
            $this->get('/docs/'.$slug)->assertOk();
        }
        $this->get('/docs/bundles')->assertSee('How bundles reach checkout')->assertSee('Progressive gifts →');
        $this->get('/docs/developers')->assertSee('orderorbit:added-to-cart');
        config(['site.preview_password' => '']);
        $this->get('/sitemap.xml')->assertSee('/docs/customer-accounts');
    }
}
