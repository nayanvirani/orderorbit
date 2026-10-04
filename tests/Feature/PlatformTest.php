<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Automation\Workflow;
use App\Models\Automation\WorkflowRun;
use App\Models\Store;
use App\Models\WebhookReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(fn (Request $r) => Http::response(['data' => []]));
        $this->store = $this->installedStore(['plan' => 'scale', 'pixel_token' => str_repeat('a', 40), 'web_pixel_id' => 'gid://shopify/WebPixel/1']);
    }

    private function makeRun(Workflow $workflow, string $key, string $status, string $subject = 'Order'): WorkflowRun
    {
        $version = \App\Models\Automation\WorkflowVersion::firstOrCreate(['workflow_id' => $workflow->id, 'version' => 1], ['definition' => [], 'program' => [], 'published_at' => now()]);

        return WorkflowRun::create(['store_id' => $this->store->id, 'workflow_id' => $workflow->id, 'version_id' => $version->id, 'trigger' => 'order_paid', 'idempotency_key' => $key, 'subject' => $subject, 'status' => $status, 'context' => [], 'results' => []]);
    }

    public function test_dashboard_shows_health_top_experiences_automation_and_next_steps(): void
    {
        $owner = $this->member($this->store, 'owner');
        $workflow = Workflow::create(['store_id' => $this->store->id, 'handle' => 'wf1', 'name' => 'Reviews', 'status' => 'enabled', 'trigger' => 'order_paid', 'draft' => ['trigger' => 'order_paid', 'steps' => []]]);
        $this->makeRun($workflow, 'k1', 'failed');
        $this->makeRun($workflow, 'k2', 'completed');

        // Home is the React shell; each card has its own JSON endpoint.
        $this->get('/app', $this->as($owner))->assertOk()->assertSee('<div id="root"></div>', false);

        $overview = $this->getJson('/app/api/dashboard/overview', $this->as($owner))->assertOk()
            ->assertJsonPath('setup.total', 5)->assertJsonPath('setup.next.label', 'Choose your goal')
            ->assertJsonFragment(['text' => '1 workflow run failed this week.', 'action' => 'See why']);
        $this->assertContains('Create a bundle', array_column($overview->json('next'), 'action'));

        $this->getJson('/app/api/dashboard/kpis?days=7', $this->as($owner))->assertOk()
            ->assertJsonPath('days', 7)->assertJsonCount(7, 'daily.revenue')->assertJsonPath('has_events', false);
        $this->getJson('/app/api/dashboard/top', $this->as($owner))->assertOk()->assertJsonPath('items', []);
        $this->getJson('/app/api/dashboard/activity', $this->as($owner))->assertOk()
            ->assertJsonPath('automation.workflows', 1)->assertJsonPath('automation.failed', 1)->assertJsonPath('automation.success_rate', 50)->assertJsonPath('tests', []);

        // Without a plan the API answers with JSON, not a redirect.
        $this->store->forceFill(['plan' => null])->save();
        $this->getJson('/app/api/dashboard/kpis', $this->as($owner))->assertStatus(402);
    }

    public function test_analytics_filters_export_and_runs_filter(): void
    {
        $owner = $this->member($this->store, 'owner');
        foreach ([['mobile', 'US'], ['desktop', 'US'], ['mobile', 'CA']] as $i => [$device, $country]) {
            AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => 'std', 'name' => 'product_viewed', 'visitor_id' => 'v'.$i, 'device' => $device, 'country' => $country, 'occurred_at' => now()->subDay()]);
        }
        $this->get('/app/analytics/events?f[device]=mobile&f[country]=us', $this->as($owner))->assertOk()->assertSee('Data as of');
        $csv = $this->get('/app/analytics/events?f[device]=mobile&export=csv', $this->as($owner))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Product viewed",product_viewed,2,2', $csv);
        $this->get('/app/analytics/revenue?export=csv', $this->as($owner))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $a = Workflow::create(['store_id' => $this->store->id, 'handle' => 'wfa', 'name' => 'Alpha flow', 'status' => 'enabled', 'trigger' => 'order_paid', 'draft' => []]);
        $b = Workflow::create(['store_id' => $this->store->id, 'handle' => 'wfb', 'name' => 'Beta flow', 'status' => 'enabled', 'trigger' => 'order_paid', 'draft' => []]);
        $this->makeRun($a, 'a', 'completed', 'Alpha subject');
        $this->makeRun($b, 'b', 'completed', 'Beta subject');
        $this->get('/app/automation/runs?workflow='.$a->id, $this->as($owner))->assertOk()->assertSee('Alpha subject')->assertDontSee('Beta subject')->assertSee('All workflows');
    }

    public function test_privacy_controls_collection_retention_export_and_deletion(): void
    {
        $owner = $this->member($this->store, 'owner');
        $this->page('/app/settings/privacy', $owner)->assertOk()->assertJsonPath('props.privacy', ['retention_months' => 13, 'browsing_events' => true, 'journeys' => true]);
        $this->post('/app/settings/privacy', ['retention_months' => 3, 'journeys' => '0'], $this->as($owner))->assertRedirectContains('notice=saved');
        $this->store->refresh();
        $this->assertSame([3, false, false], [$this->store->privacy('retention_months'), $this->store->privacy('browsing_events'), $this->store->privacy('journeys')]);

        // The collector honours them: no browsing events, no customer ids.
        $pixel = fn (array $p) => $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['t' => str_repeat('a', 40), 's' => $this->store->shop_domain, 'vid' => 'v1'] + $p));
        $pixel(['k' => 'v', 'n' => 'page_viewed']);
        $pixel(['k' => 'o', 'id' => 'gid://shopify/Order/1', 'v' => 10, 'oc' => 'gid://shopify/Customer/55', 'l' => []]);
        $this->assertSame(['checkout_completed'], AnalyticsEvent::pluck('name')->all());
        $this->assertNull(AnalyticsEvent::sole()->customer_id);

        // Retention: the nightly prune removes events older than 3 months for this store.
        AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => 'session', 'name' => 'session_started', 'occurred_at' => now()->subMonths(4)]);
        $this->artisan('orderorbit:prune-analytics')->assertSuccessful();
        $this->assertSame(1, AnalyticsEvent::count());

        $this->assertStringContainsString('checkout_completed', $this->get('/app/settings/privacy/export', $this->as($owner))->assertOk()->streamedContent());
        $this->post('/app/settings/privacy/delete-analytics', [], $this->as($owner))->assertRedirectContains('analytics_deleted');
        $this->assertSame(0, AnalyticsEvent::count());
    }

    public function test_integrations_lists_webhooks_and_extensions(): void
    {
        $owner = $this->member($this->store, 'owner');
        WebhookReceipt::create(['webhook_id' => 'w1', 'shop_domain' => $this->store->shop_domain, 'topic' => 'orders/paid', 'processed_at' => now()]);
        WebhookReceipt::create(['webhook_id' => 'w2', 'shop_domain' => $this->store->shop_domain, 'topic' => 'orders/create']);
        $hooks = collect($this->page('/app/settings/integrations', $owner)->assertOk()->assertJsonPath('props.connection.pixel', true)->json('props.webhooks'))->keyBy('topic');
        $this->assertSame([1, 0], [$hooks['orders/paid']['received'], $hooks['orders/paid']['unprocessed']]);
        $this->assertSame(1, $hooks['orders/create']['unprocessed']);
    }

    public function test_onboarding_walks_all_eight_steps(): void
    {
        $owner = $this->member($this->store, 'owner');
        $this->store->forceFill(['goal' => null])->save();
        $this->post('/app/onboarding', ['goal' => 'conversion'], $this->as($owner))->assertRedirectContains('step=3');
        $this->get('/app/onboarding', $this->as($owner))->assertOk()->assertSee('3. Choose your first experience')->assertSee('Sticky add to cart')->assertSee('type=sticky-atc', false);
        $this->get('/app/onboarding?step=5', $this->as($owner))->assertOk()->assertSee('Start by choosing your first experience.');

        $experience = app(\App\Services\Experiences\ExperienceManager::class)->create($this->store, 'trust', 'trust-row', null, 'Trust row');
        $this->get('/app/onboarding?step=7', $this->as($owner))->assertOk()->assertSee('Trust row')->assertSee('Open the builder');
        $this->get('/app/onboarding?step=8', $this->as($owner))->assertOk()->assertSee('Waiting');
        AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => 'session', 'name' => 'session_started', 'occurred_at' => now()]);
        $this->get('/app/onboarding?step=8', $this->as($owner))->assertOk()->assertSee('Received')->assertSee('Go to your dashboard');
    }

    public function test_security_headers(): void
    {
        $this->get('/admin/login')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $owner = $this->member($this->store, 'owner');
        $response = $this->get('/app', $this->as($owner));
        $this->assertFalse($response->headers->has('X-Frame-Options'), 'The embedded app must load inside Shopify admin.');
        $this->assertStringContainsString('frame-ancestors', (string) $response->headers->get('Content-Security-Policy'));
    }
}
