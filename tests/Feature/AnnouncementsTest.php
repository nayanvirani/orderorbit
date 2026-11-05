<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Models\Mail\EmailDelivery;
use App\Models\Mail\EmailProvider;
use App\Models\User;
use App\Support\EmailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G41: announcements show only in their window and audience, close per person, and follow the price change rule. */
class AnnouncementsTest extends TestCase
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
        return User::forceCreate(['name' => 'Ava', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true]);
    }

    public function test_merchants_see_it_only_in_its_window_and_audience_and_can_close_it(): void
    {
        $growth = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($growth, 'owner');
        $this->actingAs($this->admin())->post('/admin/announcements', [
            'title' => 'New: A/B tests for every offer', 'body' => 'You can now test bundle prices.', 'cta_text' => 'Try it', 'cta_url' => '/app/experiments',
            'tone' => 'info', 'placement' => 'every_page', 'audience' => 'plans', 'plans' => ['growth'],
            'starts_at' => now()->addHour()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'dismissible' => 1,
        ])->assertRedirect('/admin/announcements');
        $a = Announcement::sole();

        $this->page('/app', $owner)->assertJsonPath('shared.announcements', []);
        $a->forceFill(['starts_at' => now()->subMinute()])->save(); // now live
        $this->page('/app/automation', $owner)->assertJsonPath('shared.announcements.0.title', 'New: A/B tests for every offer')
            ->assertJsonPath('shared.announcements.0.cta.url', '/app/experiments');
        $growth->forceFill(['plan' => 'starter'])->save();
        $this->page('/app/automation', $owner)->assertJsonPath('shared.announcements', [], 'Starter stores are not in the audience.');
        $growth->forceFill(['plan' => 'growth'])->save();
        $this->assertSame(1, AnnouncementView::count(), 'Seen is recorded once per person.');

        $this->post("/app/announcements/{$a->id}/dismiss", [], $this->as($owner));
        $this->page('/app/automation', $owner)->assertJsonPath('shared.announcements', []);
        $this->postJson("/app/api/announcements/{$a->id}/click", [], $this->as($owner))->assertOk();
        $this->assertSame(1, $a->fresh()->clicks);

        $a->forceFill(['ends_at' => now()->subMinute()])->save(); // now ended
        $this->page('/app/automation', $this->member($growth, 'admin'))->assertJsonPath('shared.announcements', [], 'Ended.');
    }

    public function test_price_changes_need_30_days_cannot_be_closed_and_email_every_owner(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $owner->forceFill(['email' => 'owner@example.com'])->save();
        $admin = $this->admin();
        $base = ['title' => 'Growth plan price changes on 1 Jan', 'body' => 'From 1 January Growth costs $44.99.', 'tone' => 'info', 'placement' => 'home', 'audience' => 'plans', 'plans' => ['growth'], 'price_change' => 1, 'dismissible' => 1];

        $this->actingAs($admin)->post('/admin/announcements', $base + ['starts_at' => now()->format('Y-m-d\TH:i'), 'price_change_at' => now()->addDays(20)->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('starts_at');
        $this->actingAs($admin)->post('/admin/announcements', $base + ['starts_at' => now()->format('Y-m-d\TH:i'), 'price_change_at' => now()->addDays(31)->format('Y-m-d\TH:i')])->assertRedirect();
        $a = Announcement::sole();
        $this->assertSame([false, true, 'every_page'], [$a->dismissible, $a->email_owners, $a->placement]);
        $this->assertTrue($a->ends_at->equalTo($a->price_change_at));

        $this->artisan('orderorbit:announcement-emails')->assertSuccessful();
        $this->artisan('orderorbit:announcement-emails')->assertSuccessful();
        $this->assertSame(1, EmailDelivery::where('category', 'announcement')->where('to_hash', EmailDelivery::hash('owner@example.com'))->count(), 'Emailed once.');

        $this->page('/app/automation', $owner)->assertJsonPath('shared.announcements.0.dismissible', false);
        $this->post("/app/announcements/{$a->id}/dismiss", [], $this->as($owner));
        $this->page('/app/automation', $owner)->assertJsonPath('shared.announcements.0.title', 'Growth plan price changes on 1 Jan');
        $this->actingAs($admin)->get("/admin/announcements/{$a->id}/views")->assertOk()->assertSee($store->shop_domain);
    }
}
