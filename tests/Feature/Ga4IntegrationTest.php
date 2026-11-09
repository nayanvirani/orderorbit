<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Integrations\StoreIntegration;
use App\Services\Integrations\Ga4;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Google Analytics 4 (G32). */
class Ga4IntegrationTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        FeatureFlag::create(['key' => 'integrations', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->withoutDefer();
    }

    public function test_connecting_checks_the_keys_with_google_and_stores_them_encrypted(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $owner = $this->member($store, 'owner');
        Http::fake(['www.google-analytics.com/debug/*' => Http::sequence()->push(['validationMessages' => [['description' => 'Invalid api_secret']]])->push(['validationMessages' => []])]);

        $this->post('/app/settings/integrations/ga4/connect', ['measurement_id' => 'UA-123', 'api_secret' => 'x'], $this->as($owner))->assertSessionHasErrors('connect');
        $this->post('/app/settings/integrations/ga4/connect', ['measurement_id' => 'G-ABC1234', 'api_secret' => 'bad'], $this->as($owner))->assertSessionHasErrors('connect');
        $this->post('/app/settings/integrations/ga4/connect', ['measurement_id' => 'g-abc1234', 'api_secret' => 'good'], $this->as($owner))->assertRedirect();

        $i = StoreIntegration::sole();
        $this->assertSame('G-ABC1234', $i->credentials['measurement_id']);
        $this->assertStringNotContainsString('good', (string) DB::table('store_integrations')->value('credentials'), 'Keys are encrypted at rest.');

        $this->post('/app/settings/integrations/ga4/disconnect', [], $this->as($owner))->assertRedirect();
        $this->assertSame(0, StoreIntegration::count(), 'Disconnecting deletes the keys.');

        $starter = $this->installedStore(['shop_domain' => 's.myshopify.com', 'plan' => 'starter']);
        $this->assertNull(Ga4::for($starter), 'Growth and up.');
    }

    public function test_offer_events_from_the_pixel_go_to_ga4_with_the_client_id(): void
    {
        $store = $this->installedStore(['plan' => 'growth', 'pixel_token' => str_repeat('a', 40), 'web_pixel_id' => 'gid://shopify/WebPixel/1']);
        StoreIntegration::create(['store_id' => $store->id, 'provider' => 'ga4', 'credentials' => ['measurement_id' => 'G-ABC1234', 'api_secret' => 's'],
            'settings' => ['events' => ['growvia_offer_view' => true, 'growvia_offer_add' => false, 'growvia_purchase' => true, 'growvia_experiment_variant' => true]]]);
        $sent = [];
        Http::fake(function (Request $r) use (&$sent) {
            if (str_contains($r->url(), 'google-analytics.com/mp/collect')) {
                $sent[] = $r->data();
            }

            return Http::response('', 204);
        });
        $pixel = fn (array $p) => $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['t' => str_repeat('a', 40), 's' => $store->shop_domain, 'ga' => 'GA1.1.123456789.1700000000'] + $p));

        $pixel(['k' => 'e', 'e' => 'growvia:experience_viewed', 'x' => 'bnd1', 'ty' => 'bundles']);
        $pixel(['k' => 'e', 'e' => 'growvia:added_to_cart', 'x' => 'bnd1', 'q' => 2]); // turned off
        $pixel(['k' => 'o', 'id' => 'gid://shopify/Order/5', 'v' => 120.5, 'c' => 'USD', 'l' => [['q' => 1, 'v' => 80, 'o' => 'bnd1']]]);

        $this->assertCount(2, $sent);
        $this->assertSame(['123456789.1700000000', 'growvia_offer_view', 'bnd1'], [$sent[0]['client_id'], $sent[0]['events'][0]['name'], $sent[0]['events'][0]['params']['offer_id']]);
        $this->assertEquals(['growvia_purchase', 80, 'USD'], [$sent[1]['events'][0]['name'], $sent[1]['events'][0]['params']['value'], $sent[1]['events'][0]['params']['currency']]);

        // No GA cookie (or no consent, so no pixel): nothing is sent.
        $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['t' => str_repeat('a', 40), 's' => $store->shop_domain, 'k' => 'e', 'e' => 'growvia:experience_viewed', 'x' => 'bnd2']));
        $this->assertCount(2, $sent);
        $this->assertSame('123456789.1700000000', Ga4::clientId('GA1.2.123456789.1700000000'));
    }
}
