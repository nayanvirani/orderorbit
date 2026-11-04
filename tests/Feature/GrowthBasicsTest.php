<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Mail\EmailProvider;
use App\Models\FeatureFlag;
use App\Services\Experiences\ExperienceManager;
use App\Services\Lifecycle\LifecycleEmails;
use App\Support\EmailSettings;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G23 review request after a real milestone, and G24 lifecycle emails sent once while their step is undone. */
class GrowthBasicsTest extends TestCase
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

    private function flag(string $key): void
    {
        FeatureFlag::create(['key' => $key, 'mode' => 'all', 'enabled' => true]);
        Features::forget();
    }

    public function test_the_review_request_waits_for_a_milestone_and_records_shopifys_answer(): void
    {
        $store = $this->installedStore(['installed_at' => now()->subDays(20)]);
        $owner = $this->member($store, 'owner');
        $this->flag('review_prompt');
        $this->getJson('/app/api/dashboard/overview', $this->as($owner))->assertJsonPath('milestone', null);

        AnalyticsEvent::create(['store_id' => $store->id, 'event' => 'attributed', 'name' => 'growvia:revenue_attributed', 'experience_handle' => 'x', 'order_ref' => 'o1', 'value' => 1200, 'visitor_id' => 'v1', 'occurred_at' => now()->subDays(2)]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->getJson('/app/api/dashboard/overview', $this->as($owner))->assertJsonPath('milestone.key', 'first_1000');

        $this->postJson('/app/api/dashboard/review', ['milestone' => 'first_1000', 'code' => 'success'], $this->as($owner))->assertOk();
        $this->assertSame('success', $store->fresh()->review_result);
        $this->getJson('/app/api/dashboard/overview', $this->as($owner))->assertJsonPath('milestone', null);
    }

    public function test_lifecycle_emails_go_once_while_their_step_is_undone_and_can_be_turned_off(): void
    {
        $store = $this->installedStore(['installed_at' => now()->subDays(5), 'email' => 'owner@shop.test']);
        $emails = app(LifecycleEmails::class);
        $this->assertSame(0, $emails->run(), 'Off without the flag.');
        $this->flag('lifecycle_emails');

        $this->assertArrayHasKey('no_widget', $emails->due($store->fresh()));
        $this->assertArrayNotHasKey('welcome', $emails->due($store->fresh()), 'Only new installs get the welcome.');
        $this->assertGreaterThan(0, $emails->run());
        $this->assertSame(0, $emails->run(), 'Sent once.');
        $this->assertSame('sent', DB::table('lifecycle_emails')->where('kind', 'no_widget')->value('status'));

        // A draft the plan can't publish: the note names the plan that unlocks it.
        $store->forceFill(['plan' => 'free'])->save();
        $draft = app(ExperienceManager::class)->create($store->fresh(), 'cart-upsells', array_key_first(\App\Experiences\Registry::type('cart-upsells')['templates']), null);
        $draft->forceFill(['created_at' => now()->subDays(2)])->save();
        $due = $emails->due($store->fresh());
        $this->assertStringContainsString('Starter plan', $due['upgrade_preview'][1]);

        // The link in the email turns tips off.
        $this->get(URL::signedRoute('lifecycle.unsubscribe', ['store' => $store->id]))->assertOk();
        $this->get(route('lifecycle.unsubscribe', ['store' => $store->id]))->assertForbidden();
        $this->assertTrue($store->fresh()->lifecycle_unsubscribed);
        $this->assertSame([], $emails->due($store->fresh()));
    }

    public function test_free_previews_name_the_plan_that_unlocks_them(): void
    {
        $store = $this->installedStore(['plan' => 'free']);
        $owner = $this->member($store, 'owner');
        $draft = app(ExperienceManager::class)->create($store, 'cart-upsells', array_key_first(\App\Experiences\Registry::type('cart-upsells')['templates']), null);
        $this->page("/app/cro/experiences/{$draft->id}/edit", $owner)
            ->assertJsonPath('props.preview.plan', 'Starter')->assertJsonPath('props.preview.feature', 'Cart upsells & cross-sell');
        $trust = app(ExperienceManager::class)->create($store, 'trust', 'trust-row', null);
        $this->page("/app/cro/experiences/{$trust->id}/edit", $owner)->assertJsonPath('props.preview', null);
    }
}
