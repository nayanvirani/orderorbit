<?php

namespace Tests\Feature;

use App\Models\Audiences\Segment;
use App\Models\FeatureFlag;
use App\Models\Integrations\StoreIntegration;
use App\Services\Integrations\Klaviyo;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Klaviyo (G31), against faked Klaviyo and Shopify APIs. */
class KlaviyoIntegrationTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        FeatureFlag::create(['key' => 'integrations', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->withoutDefer();
        Http::fake(function (Request $r) {
            $url = $r->url();
            if (str_contains($url, 'a.klaviyo.com')) {
                $this->calls[] = [$r->method(), preg_replace('#^https://a.klaviyo.com/api#', '', strtok($url, '?')), $r->data()];
                if (str_contains($url, '/lists/L1/profiles')) {
                    return Http::response(['data' => [['id' => 'P-old', 'attributes' => ['email' => 'gone@example.com']], ['id' => 'P-keep', 'attributes' => ['email' => 'ana@example.com']]], 'links' => ['next' => null]]);
                }
                if ($r->method() === 'POST' && str_ends_with(strtok($url, '?'), '/lists/')) {
                    return Http::response(['data' => ['id' => 'L1']], 201);
                }

                // The old list was deleted in Klaviyo.
                return Http::response(['data' => []], str_contains($url, '/lists/L-deleted/') ? 404 : 200);
            }
            $q = $r['query'] ?? '';
            if (str_contains($q, 'customer(id:')) {
                return Http::response(['data' => ['customer' => ['email' => 'ana@example.com']]]);
            }
            if (str_contains($q, 'customers(first')) {
                return Http::response(['data' => ['customers' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => [
                    ['email' => 'Ana@Example.com', 'emailMarketingConsent' => ['marketingState' => 'SUBSCRIBED']],
                    ['email' => 'new@example.com', 'emailMarketingConsent' => ['marketingState' => 'SUBSCRIBED']],
                    ['email' => 'nope@example.com', 'emailMarketingConsent' => ['marketingState' => 'NOT_SUBSCRIBED']],
                ]]]]);
            }

            return Http::response(['data' => []]);
        });
    }

    public function test_connecting_and_events_for_signed_in_customers(): void
    {
        $store = $this->installedStore(['plan' => 'growth', 'pixel_token' => str_repeat('a', 40), 'web_pixel_id' => 'gid://shopify/WebPixel/1']);
        $owner = $this->member($store, 'owner');
        $this->post('/app/settings/integrations/klaviyo/connect', ['api_key' => 'not-a-key'], $this->as($owner))->assertSessionHasErrors('connect');
        $this->post('/app/settings/integrations/klaviyo/connect', ['api_key' => 'pk_'.str_repeat('a', 30)], $this->as($owner))->assertRedirect();
        $this->assertTrue((bool) Klaviyo::for($store));

        $pixel = fn (array $p) => $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['t' => str_repeat('a', 40), 's' => $store->shop_domain] + $p));
        $pixel(['k' => 'e', 'e' => 'growvia:reward_unlocked', 'x' => 'gift1', 'cust' => 'gid://shopify/Customer/77']);
        $pixel(['k' => 'e', 'e' => 'growvia:experience_viewed', 'x' => 'gift1']); // not signed in: nothing
        $events = array_values(array_filter($this->calls, fn ($c) => $c[1] === '/events/'));
        $this->assertCount(1, $events);
        $attrs = $events[0][2]['data']['attributes'];
        $this->assertSame(['Growvia: Reward unlocked', 'ana@example.com', 'gift1'], [$attrs['metric']['data']['attributes']['name'], $attrs['profile']['data']['attributes']['email'], $attrs['properties']['offer']]);
    }

    public function test_segments_sync_to_lists_with_subscribers_only(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);
        $segment = Segment::create(['store_id' => $store->id, 'name' => 'VIP', 'match' => 'all', 'rules' => [['field' => 'ltv', 'op' => 'gte', 'value' => 200]]]);
        $i = StoreIntegration::create(['store_id' => $store->id, 'provider' => 'klaviyo', 'credentials' => ['api_key' => 'pk_x'],
            'settings' => ['events' => [], 'segments' => [$segment->id], 'lists' => [$segment->id => 'L-deleted']]]);

        $this->artisan('orderorbit:klaviyo-lists')->assertSuccessful();

        $import = collect($this->calls)->first(fn ($c) => $c[1] === '/profile-bulk-import-jobs/');
        $emails = array_map(fn ($p) => $p['attributes']['email'], $import[2]['data']['attributes']['profiles']['data']);
        $this->assertSame(['new@example.com'], $emails, 'Only subscribers not yet on the list are added.');
        $removed = collect($this->calls)->first(fn ($c) => $c[0] === 'DELETE');
        $this->assertSame([['type' => 'profile', 'id' => 'P-old']], $removed[2]['data'], 'People who no longer match are removed.');
        $this->assertSame('L1', $i->fresh()->setting('lists')[$segment->id], 'A deleted list is made again.');
        $this->assertStringContainsString('VIP: 2 in the list (1 added, 1 removed)', $i->fresh()->log[0]['text']);
    }
}
