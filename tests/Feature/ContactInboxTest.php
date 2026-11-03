<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\Mail\EmailDelivery;
use App\Models\Mail\EmailProvider;
use App\Models\User;
use App\Support\EmailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G25: website contact messages reach the Internal Admin inbox and the team's email. */
class ContactInboxTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        EmailSettings::save(['enabled' => true, 'strategy' => 'priority', 'from_email' => 'hello@orderorbit.space', 'from_name' => 'Growvia', 'reply_to' => '', 'team_email' => 'team@orderorbit.space']);
        EmailProvider::create(['name' => 'Resend', 'driver' => 'resend', 'credentials' => ['api_key' => 're_1'], 'priority' => 10, 'is_active' => true]);
        Http::fake(fn (Request $r) => str_contains($r->url(), 'api.resend.com') ? Http::response(['id' => 'rs_'.uniqid()]) : Http::response(['data' => []]));
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Ava Team', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true]);
    }

    public function test_a_website_message_reaches_the_inbox_and_the_team_email(): void
    {
        $store = $this->installedStore();
        $this->post('/contact', ['name' => 'Ana R.', 'email' => 'ana@example.com', 'topic' => 'Support', 'store_url' => 'https://'.$store->shop_domain.'/', 'message' => 'Can a box mix two collections?'])
            ->assertSessionHas('contact_sent');

        $m = ContactSubmission::sole();
        $this->assertSame(['new', $store->id], [$m->status, $m->store_id]);
        $alert = EmailDelivery::where('category', 'team_alert')->sole();
        $this->assertSame('team@orderorbit.space', $alert->to_email);
        $this->assertStringContainsString('New contact message: Support from Ana R.', $alert->subject);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/contact')->assertOk()->assertSee('Ana R.')->assertSee('Can a box mix');
        $this->actingAs($admin)->get('/admin/contact/'.$m->id)->assertOk()->assertSee($store->shop_domain);
        $this->assertSame('read', $m->fresh()->status, 'Opening a new message marks it read.');
    }

    public function test_the_team_replies_by_email_and_the_status_follows(): void
    {
        $m = ContactSubmission::create(['name' => 'Sam', 'email' => 'sam@example.com', 'topic' => 'Sales', 'message' => 'Do you have a yearly plan?']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/contact/{$m->id}/reply", ['body' => 'Hi Sam, yes: two months free on yearly plans.'])
            ->assertRedirect("/admin/contact/{$m->id}");
        $m->refresh();
        $this->assertSame(['replied', $admin->id], [$m->status, $m->replied_by]);
        $sent = EmailDelivery::where('category', 'contact_reply')->sole();
        $this->assertSame(['sam@example.com', 'Re: your message to Growvia'], [$sent->to_email, $sent->subject]);

        $this->actingAs($admin)->post("/admin/contact/{$m->id}/status", ['status' => 'closed'])->assertRedirect();
        $this->assertSame('closed', $m->fresh()->status);
        $this->actingAs($admin)->get('/admin/contact?status=closed')->assertOk()->assertSee('Sam');
        $this->actingAs($admin)->get('/admin/contact?status=new')->assertOk()->assertDontSee('Do you have a yearly plan');
    }

    public function test_team_alerts_respect_their_cooldown(): void
    {
        $this->assertTrue(\App\Support\TeamAlert::send('Queue backed up', 'details', 'queue', 30));
        $this->assertFalse(\App\Support\TeamAlert::send('Queue backed up', 'details', 'queue', 30), 'The same alert waits for its cooldown.');
        $this->assertSame(1, EmailDelivery::where('category', 'team_alert')->count());
    }
}
