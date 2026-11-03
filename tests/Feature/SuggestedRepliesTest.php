<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\SavedReply;
use App\Models\Support\Ticket;
use App\Models\User;
use App\Services\Support\SupportDesk;
use App\Support\ReplySuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G35: "Suggest reply" picks matching saved replies and help answers, filled with the store's details. */
class SuggestedRepliesTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(fn (Request $r) => Http::response(['data' => []]));
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Ava Team', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true]);
    }

    public function test_the_best_matches_come_first_and_are_filled_with_the_store(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $s = app(ReplySuggester::class);

        $top = $s->suggest('Hi, the bundle discount is not applied at checkout for one variant', ReplySuggester::context('Maya Rao', $store));
        $this->assertCount(3, $top);
        $this->assertSame('Bundle discount missing at checkout', $top[0]['title']);
        $this->assertStringStartsWith('Hi Maya,', $top[0]['body']);

        $widget = $s->suggest('My widget is not showing on my store', ReplySuggester::context('Sam', $store))[0];
        $this->assertSame('Widget not showing on the store', $widget['title']);
        $this->assertStringContainsString($store->shop_domain, $widget['body']);
        $this->assertSame([], $widget['missing']);

        // Without a store, store placeholders are flagged instead of sent half-filled.
        $noStore = collect($s->suggest('My widget is not showing on my store', ReplySuggester::context('Sam', null)))->firstWhere('title', 'Widget not showing on the store');
        $this->assertSame(['shop', 'live_widgets'], $noStore['missing']);
        $this->assertStringContainsString('[FILL: shop]', $noStore['body']);

        // Help-centre answers are candidates too.
        $help = $s->suggest('Will the app change my theme code?')[0];
        $this->assertStringStartsWith('Help centre:', $help['source']);
    }

    public function test_suggestions_show_on_tickets_and_contact_messages_and_used_replies_rank_higher(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        $ticket = app(SupportDesk::class)->open($store, $owner, ['subject' => 'Plan question', 'body' => 'How do I change my plan to Scale?', 'category' => 'billing'], []);
        $admin = $this->admin();

        $this->actingAs($admin)->get("/admin/tickets/{$ticket->id}")->assertOk()->assertSee('Suggest reply')->assertDontSee('Use this reply');
        $this->actingAs($admin)->get("/admin/tickets/{$ticket->id}?suggest=1")->assertOk()->assertSee('Use this reply')->assertSee('Change plan');

        $reply = SavedReply::where('title', 'Change plan')->sole();
        $this->actingAs($admin)->post("/admin/tickets/{$ticket->id}/reply", ['body' => 'Hi, switch in Settings.', 'saved_reply_id' => (string) $reply->id])->assertRedirect();
        $this->assertSame(1, $reply->fresh()->use_count);

        $m = ContactSubmission::create(['name' => 'Ana', 'email' => 'ana@example.com', 'topic' => 'Support', 'message' => 'Sales pop is not showing any notifications']);
        $this->actingAs($admin)->get("/admin/contact/{$m->id}?suggest=1")->assertOk()->assertSee('Sales pop not showing');
    }

    public function test_the_team_manages_saved_replies(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/replies')->assertOk()->assertSee('Bundle discount missing at checkout');
        $this->actingAs($admin)->post('/admin/replies', ['title' => 'Gift tin at six items', 'keywords' => 'gift tin box six', 'body' => 'Hi {{first_name}}, not yet.'])->assertRedirect('/admin/replies');
        $new = SavedReply::where('title', 'Gift tin at six items')->sole();
        $this->assertSame('Gift tin at six items', app(ReplySuggester::class)->suggest('Can the box show a gift tin at six items?')[0]['title']);
        $this->actingAs($admin)->post("/admin/replies/{$new->id}/delete")->assertRedirect('/admin/replies');
        $this->assertNull(SavedReply::find($new->id));
    }
}
