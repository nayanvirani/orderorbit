<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Services\Analytics\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private function pixel(array $payload)
    {
        return $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode($payload));
    }

    public function test_pixel_events_and_order_attribution(): void
    {
        $this->setUpShopify();
        $store = $this->installedStore(['pixel_token' => str_repeat('a', 40), 'web_pixel_id' => 'gid://shopify/WebPixel/1']);
        $auth = ['t' => str_repeat('a', 40), 's' => $store->shop_domain];

        $this->pixel(['t' => 'wrong'] + $auth)->assertNoContent();
        $this->assertSame(0, AnalyticsEvent::count(), 'A wrong token records nothing.');

        $this->pixel($auth + ['k' => 's'])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', '*');
        $this->pixel($auth + ['k' => 'e', 'e' => 'orderorbit:experience_viewed', 'x' => 'bnd1']);
        $this->pixel($auth + ['k' => 'e', 'e' => 'orderorbit:added_to_cart', 'x' => 'bnd1', 'q' => 2]);
        $this->pixel($auth + ['k' => 'e', 'e' => 'orderorbit:experience_viewed', 'x' => 'gift1']);
        $this->pixel($auth + ['k' => 'e', 'e' => 'reward_unlocked', 'x' => 'gift1']);
        $this->pixel($auth + ['k' => 'e', 'e' => 'upsell_accepted', 'x' => 'bnd1:u']);
        $this->pixel($auth + ['k' => 'e', 'e' => 'upsell_declined', 'x' => 'bnd1']);
        $this->pixel($auth + ['k' => 'e', 'e' => 'not_an_event', 'x' => 'bnd1']);

        $order = $auth + ['k' => 'o', 'id' => 'gid://shopify/Order/5', 'v' => 120.5, 'c' => 'USD', 'l' => [
            ['q' => 2, 'v' => 52.2, 'o' => 'bnd1', 'b' => 'bnd1'],   // quantity-break lines
            ['q' => 1, 'v' => 40, 'o' => 'bnd1'],                    // merged bundle line
            ['q' => 1, 'v' => 0, 'o' => 'gift1'],                    // free gift
            ['q' => 1, 'v' => 28.3, 'o' => null],                    // not from an offer
        ]];
        $this->pixel($order);
        $this->pixel($order); // the thank-you page can fire twice

        $this->assertSame(1, AnalyticsEvent::where('event', 'order')->count());
        $this->assertEquals(92.2, AnalyticsEvent::where('event', 'attributed')->where('experience_handle', 'bnd1')->value('value'));

        $summary = app(Analytics::class)->summary($store);
        $this->assertSame(1, $summary['orders']);
        $this->assertSame(1, $summary['influenced_orders']);
        $this->assertEquals(92.2, $summary['influenced_revenue']);
        $this->assertEquals(100.0, $summary['conversion'], '1 order from 1 session.');
        $this->assertSame(['views' => 1, 'clicks' => 0, 'adds' => 1, 'unlocks' => 0, 'accepts' => 1, 'declines' => 1, 'orders' => 1, 'revenue' => 92.2], $summary['experiences']['bnd1']);
        $this->assertSame(1, $summary['experiences']['gift1']['unlocks']);

        $owner = $this->member($store, 'owner');
        $this->get('/app/analytics', $this->as($owner))->assertOk()->assertSee('Revenue from offers')->assertSee('$92.20');
        $this->get('/app', $this->as($owner))->assertOk()->assertSee('$92.20');
    }

    public function test_the_pixel_connects_with_a_store_token(): void
    {
        $this->setUpShopify();
        Http::fake(fn (Request $r) => str_contains($r['query'] ?? '', 'webPixelCreate')
            ? Http::response(['data' => ['webPixelCreate' => ['webPixel' => ['id' => 'gid://shopify/WebPixel/9'], 'userErrors' => []]]])
            : Http::response(['data' => []]));
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');

        $this->get('/app/analytics', $this->as($owner))->assertOk()->assertDontSee('isn&#039;t connected', false);

        $store->refresh();
        $this->assertSame('gid://shopify/WebPixel/9', $store->web_pixel_id);
        Http::assertSent(fn (Request $r) => str_contains($r['query'] ?? '', 'webPixelCreate')
            && json_decode(json_decode($r->body(), true)['variables']['settings'], true) === ['token' => $store->pixel_token]);
    }
}
