<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Endpoints shoppers hit on every storefront page stay small: no session, no cookies, batched events. */
class LeanEndpointsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_storefront_endpoints_send_no_cookies_and_pixel_events_arrive_batched(): void
    {
        $this->setUpShopify();
        $store = $this->installedStore(['pixel_token' => str_repeat('a', 40)]);
        $base = ['vid' => 'v1', 'sid' => 's1', 'dev' => 'mobile', 'path' => '/products/serum', 'src' => 'direct'];

        $response = $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['t' => str_repeat('a', 40), 's' => $store->shop_domain, 'b' => [
            ['k' => 's'] + $base,
            ['k' => 'v', 'n' => 'page_viewed'] + $base,
            ['k' => 'v', 'n' => 'product_viewed', 'p' => '42', 'v' => 29] + $base,
            'not an event',
        ]]));
        $response->assertNoContent();
        $this->assertSame([], $response->headers->getCookies(), 'No session or XSRF cookie on pixel responses.');
        $this->assertSame(['session_started', 'page_viewed', 'product_viewed'], AnalyticsEvent::orderBy('id')->pluck('name')->all());

        // A single event still works (older pixel versions), and a wrong token stores nothing.
        $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['t' => str_repeat('a', 40), 's' => $store->shop_domain, 'k' => 'v', 'n' => 'cart_viewed'] + $base))->assertNoContent();
        $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['t' => 'nope', 's' => $store->shop_domain, 'b' => [['k' => 's'] + $base]]))->assertNoContent();
        $this->assertSame(4, AnalyticsEvent::count());

        $feed = $this->get('/api/sales-pop?shop='.$store->shop_domain)->assertOk();
        $this->assertSame([], $feed->headers->getCookies());
        $this->assertStringContainsString('max-age=300', $feed->headers->get('Cache-Control'));

        // The website keeps its session (forms need it).
        $this->assertNotEmpty($this->get('/contact')->headers->getCookies());
    }
}
