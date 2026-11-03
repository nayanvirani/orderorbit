<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Mail\EmailDelivery;
use App\Models\Mail\EmailProvider;
use App\Models\Support\Ticket;
use App\Models\User;
use App\Services\Support\LiveChat;
use App\Support\EmailSettings;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G43: store owners chat with the team from any page; the team answers from Internal Admin › Live chat. */
class LiveChatTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        EmailSettings::save(['enabled' => true, 'strategy' => 'priority', 'from_email' => 'hello@orderorbit.space', 'from_name' => 'Growvia', 'reply_to' => '', 'team_email' => 'team@orderorbit.space']);
        EmailProvider::create(['name' => 'Resend', 'driver' => 'resend', 'credentials' => ['api_key' => 're_1'], 'priority' => 10, 'is_active' => true]);
        Http::fake(fn (Request $r) => str_contains($r->url(), 'api.resend.com') ? Http::response(['id' => 'rs_'.uniqid()]) : Http::response(['data' => []]));
        LiveChat::saveSettings(['days' => [1, 2, 3, 4, 5, 6, 7], 'from' => '00:00', 'to' => '23:59', 'timezone' => 'UTC']);
    }

    private function admin(bool $online = true): User
    {
        return User::forceCreate(['name' => 'Ava Team', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true,
            'chat_status' => $online ? 'online' : 'away', 'chat_seen_at' => now()]);
    }

    private function release($store): void
    {
        FeatureFlag::create(['key' => 'live_chat', 'mode' => 'stores', 'store_ids' => [$store->id], 'enabled' => true]);
        Features::forget();
    }

    public function test_a_store_owner_chats_with_the_team_and_gets_a_copy(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner', ['first_name' => 'Maya', 'email' => 'maya@example.com']);
        $this->getJson('/app/api/chat', $this->as($owner))->assertNotFound();
        $this->page('/app/automation', $owner)->assertJsonPath('shared.chat', false);

        $this->release($store);
        $admin = $this->admin();
        $this->page('/app/automation', $owner)->assertJsonPath('shared.chat', true);
        $this->getJson('/app/api/chat', $this->as($owner))->assertOk()->assertJsonPath('status', 'online')->assertJsonPath('chat', null);

        $this->post('/app/api/chat', ['body' => 'My bundle discount is not applied at checkout', 'topic' => 'setup', 'page' => '/app/bundles?x=1'], $this->as($owner) + ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('chat.state', 'waiting')->assertJsonPath('chat.position', 1)->assertJsonPath('chat.messages.0.author', 'You');
        $chat = Ticket::sole();
        $this->assertSame(['chat', '/app/bundles', 'setup'], [$chat->channel, $chat->page, $chat->category]);

        // The team sees it in the queue with the store's details and suggested replies.
        $this->actingAs($admin)->get("/admin/chat?chat={$chat->id}")->assertOk()->assertSee($store->shop_domain)->assertSee('My bundle discount')
            ->assertSee('Store details')->assertSee('Bundle discount missing at checkout');
        $this->actingAs($admin)->getJson('/admin/chat/feed')->assertOk()->assertJsonPath('waiting', [$chat->id]);
        $this->actingAs($admin)->get('/admin/tickets')->assertOk()->assertDontSee('My bundle discount');

        $this->actingAs($admin)->postJson("/admin/chat/{$chat->id}/send", ['body' => 'Hi Maya, checking your store now.'])->assertOk();
        $this->actingAs($admin)->postJson("/admin/chat/{$chat->id}/send", ['body' => 'Plan: Growth, discount function ok', 'internal' => 1])->assertOk();
        $chat->refresh();
        $this->assertSame(['active', $admin->id], [$chat->chat_state, $chat->assigned_to]);
        $this->assertNotNull($chat->first_reply_at);
        $this->assertSame(0, EmailDelivery::where('category', 'chat_reply')->count(), 'No email while they are in the chat.');

        $this->getJson('/app/api/chat', $this->as($owner))->assertJsonPath('unread', 1)->assertJsonCount(2, 'chat.messages');
        $this->getJson('/app/api/chat?open=1', $this->as($owner));
        $this->getJson('/app/api/chat', $this->as($owner))->assertJsonPath('unread', 0);

        $this->postJson("/app/api/chat/{$chat->id}/close", [], $this->as($owner))->assertOk()->assertJsonPath('chat.state', 'closed');
        $this->postJson("/app/api/chat/{$chat->id}/rate", ['rating' => 1], $this->as($owner))->assertOk()->assertJsonPath('chat.rating', 1);
        $copy = EmailDelivery::where('category', 'chat_transcript')->sole();
        $this->assertSame('maya@example.com', $copy->to_email);
        $this->assertSame('resolved', $chat->fresh()->status);
    }

    public function test_outside_hours_the_team_is_told_and_replies_go_by_email(): void
    {
        LiveChat::saveSettings(['days' => [], 'from' => '09:00', 'to' => '18:00', 'timezone' => 'Asia/Kolkata']);
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner', ['first_name' => 'Maya', 'email' => 'maya@example.com']);
        $this->release($store);
        $admin = $this->admin(false);

        $this->getJson('/app/api/chat', $this->as($owner))->assertJsonPath('status', 'offline');
        $this->post('/app/api/chat', ['body' => 'Can I change my plan?', 'topic' => 'billing'], $this->as($owner) + ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('chat.state', 'offline');
        $chat = Ticket::sole();
        $this->assertStringContainsString('outside business hours', EmailDelivery::where('category', 'team_alert')->sole()->subject);

        $chat->forceFill(['merchant_seen_at' => now()->subHour()])->save();
        $this->actingAs($admin)->postJson("/admin/chat/{$chat->id}/send", ['body' => 'Hi Maya, yes: Settings › Billing.'])->assertOk();
        $this->assertSame('maya@example.com', EmailDelivery::where('category', 'chat_reply')->sole()->to_email);
    }

    public function test_the_sweep_alerts_about_waiting_chats_and_closes_quiet_ones(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner', ['email' => 'maya@example.com']);
        $this->release($store);
        $admin = $this->admin();
        $chat = app(LiveChat::class);

        $waiting = $chat->fromMerchant($store, $owner, 'Widget is not showing', 'technical', '/app');
        $this->assertSame(0, EmailDelivery::where('category', 'team_alert')->count(), 'Someone is online, so no email yet.');
        $waiting->forceFill(['created_at' => now()->subMinutes(6)])->save();
        $this->assertSame(['alerted' => 1, 'closed' => 0], $chat->sweep());
        $this->assertSame(['alerted' => 0, 'closed' => 0], $chat->sweep(), 'Alerted once.');

        $chat->fromTeam($waiting, $admin, 'Fixed, please check.');
        $waiting->refresh()->forceFill(['last_reply_at' => now()->subMinutes(31)])->save();
        $this->assertSame(['alerted' => 0, 'closed' => 1], $chat->sweep());
        $this->assertSame('closed', $waiting->fresh()->chat_state);

        $this->actingAs($admin)->post('/admin/chat/hours', ['days' => [1, 2, 3, 4, 5], 'from' => '09:00', 'to' => '18:00', 'timezone' => 'Asia/Kolkata'])->assertRedirect();
        $this->assertSame('Mon–Fri, 09:00–18:00 IST', LiveChat::hoursText());
    }

    public function test_a_chat_can_become_a_ticket_and_other_stores_cannot_reach_it(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $this->release($store);
        $admin = $this->admin();
        $chat = app(LiveChat::class)->fromMerchant($store, $owner, 'Checkout block broke after theme update', 'extension', null);

        $other = $this->installedStore(['shop_domain' => 'other.myshopify.com']);
        $this->assertNull(app(LiveChat::class)->current($other, null));

        $this->actingAs($admin)->post("/admin/chat/{$chat->id}/ticket")->assertRedirect("/admin/tickets/{$chat->id}");
        $chat->refresh();
        $this->assertSame(['ticket', 'open', 'Checkout block broke after theme update'], [$chat->channel, $chat->status, $chat->subject]);
        $this->actingAs($admin)->get('/admin/tickets')->assertSee('Checkout block broke');
    }
}
