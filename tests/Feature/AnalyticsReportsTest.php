<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsFunnel;
use App\Models\Store;
use App\Services\Analytics\Attribution;
use App\Services\Analytics\Events;
use App\Services\Analytics\Explorer;
use App\Services\Analytics\Funnels;
use App\Services\Analytics\Journeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class AnalyticsReportsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private Store $store;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(fn (Request $r) => Http::response(['data' => []]));
        $this->store = $this->installedStore(['plan' => 'growth', 'pixel_token' => str_repeat('a', 40), 'web_pixel_id' => 'gid://shopify/WebPixel/1']);
        $this->auth = ['t' => str_repeat('a', 40), 's' => $this->store->shop_domain];
    }

    private function pixel(array $payload)
    {
        return $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode($this->auth + $payload));
    }

    /** A visitor's event at a moment, written straight to the table. */
    private function event(string $visitor, string $name, string $at, array $extra = []): AnalyticsEvent
    {
        $code = match (true) {
            $name === 'checkout_completed' => 'order',
            $name === 'session_started' => 'session',
            str_starts_with($name, 'orderorbit:') => $extra['event'] ?? 'view',
            default => 'std',
        };
        unset($extra['event']);

        return AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => $code, 'name' => $name, 'visitor_id' => $visitor, 'session_id' => $extra['session_id'] ?? $visitor.'-s1', 'occurred_at' => now()->parse($at)] + $extra);
    }

    public function test_the_pixel_records_standard_events_with_visitor_session_and_source(): void
    {
        $ctx = ['vid' => 'v-123', 'sid' => 'abc1', 'dev' => 'mobile', 'src' => 'Instagram', 'med' => 'social', 'cmp' => 'Summer Sale', 'path' => '/en-ca/products/glow-serum'];
        $this->pixel($ctx + ['k' => 's'])->assertNoContent();
        $this->pixel($ctx + ['k' => 'v', 'n' => 'product_viewed', 'p' => 'gid://shopify/Product/77', 'va' => 'gid://shopify/ProductVariant/770', 'lb' => 'Glow Serum', 'v' => 29]);
        $this->pixel($ctx + ['k' => 'v', 'n' => 'search_submitted', 'lb' => '<b>serum</b>', 'path' => '/search']);
        $this->pixel($ctx + ['k' => 'v', 'n' => 'collection_viewed', 'cid' => 'gid://shopify/Collection/9', 'lb' => 'Skincare', 'path' => '/collections/skincare']);
        $this->pixel($ctx + ['k' => 'v', 'n' => 'checkout_completed']); // purchases only come from the order message
        $this->pixel($ctx + ['k' => 'v', 'n' => 'made_up_event']);
        $this->pixel($ctx + ['k' => 'e', 'e' => 'experience_viewed', 'x' => 'bnd1', 'ty' => 'bundles', 'tp' => 'stacked-cards']);
        $this->pixel($ctx + ['k' => 'e', 'e' => 'experience_clicked', 'x' => 'sticky1', 'ty' => 'sticky-atc']);
        $this->pixel($ctx + ['k' => 'e', 'e' => 'reward_unlocked', 'x' => 'gift1', 'ty' => 'free-gifts']);
        $this->pixel($ctx + ['k' => 'o', 'id' => 'gid://shopify/Order/9', 'v' => 58, 'c' => 'USD', 'cc' => 'CA', 'oc' => 'gid://shopify/Customer/4321', 'l' => [['q' => 2, 'v' => 58, 'o' => 'bnd1']]]);

        $this->assertSame(['session_started', 'product_viewed', 'search_submitted', 'collection_viewed', 'orderorbit:bundle_viewed', 'orderorbit:sticky_atc_clicked', 'orderorbit:free_gift_unlocked', 'checkout_completed', 'orderorbit:revenue_attributed'],
            AnalyticsEvent::orderBy('id')->pluck('name')->all());

        $product = AnalyticsEvent::where('name', 'product_viewed')->sole();
        $this->assertSame(['v-123', 'abc1', 'mobile', 'instagram', 'social', 'summer sale', 'product', '77', '770', 'Glow Serum'],
            [$product->visitor_id, $product->session_id, $product->device, $product->source, $product->medium, $product->campaign, $product->page_type, $product->product_id, $product->variant_id, $product->label]);
        $this->assertSame('serum', AnalyticsEvent::where('name', 'search_submitted')->value('label'));
        $this->assertSame(['collection_id' => '9'], AnalyticsEvent::where('name', 'collection_viewed')->sole()->properties);
        $this->assertSame(['bnd1', 'bundles', 'stacked-cards', 'view'], array_values(AnalyticsEvent::where('name', 'orderorbit:bundle_viewed')->sole()->only(['experience_handle', 'experience_type', 'template', 'event'])));
        $order = AnalyticsEvent::where('event', 'order')->sole();
        $this->assertSame(['4321', 'CA', 2, 'v-123'], [$order->customer_id, $order->country, $order->quantity, $order->visitor_id]);

        $this->assertSame('orderorbit:checkout_block_viewed', Events::nameFor('experience_viewed', 'ty-survey'));
        $this->assertSame('orderorbit:experience_viewed', Events::nameFor('experience_viewed', 'countdown'));
        $this->assertSame('product', Events::pageType('/products/a'));
        $this->assertSame('index', Events::pageType('/fr/'));
    }

    public function test_event_explorer_counts_and_breakdowns(): void
    {
        $this->event('a', 'product_viewed', '-2 days', ['device' => 'mobile', 'product_id' => '1', 'label' => 'Serum']);
        $this->event('a', 'product_viewed', '-2 days', ['device' => 'mobile', 'product_id' => '2', 'label' => 'Cream']);
        $this->event('b', 'product_viewed', '-1 day', ['device' => 'desktop', 'product_id' => '1', 'label' => 'Serum']);
        $this->event('c', 'product_viewed', '-40 days'); // previous period
        $this->event('a', 'orderorbit:bundle_viewed', '-1 day', ['experience_handle' => 'bnd1']);

        $explorer = app(Explorer::class);
        $events = collect($explorer->events($this->store, now()->subDays(30), now()))->keyBy('name');
        $this->assertSame([3, 2, 1, 1], [$events['product_viewed']['total'], $events['product_viewed']['visitors'], $events['product_viewed']['sessions'] - 1, $events['product_viewed']['previous']]);
        $this->assertSame(3, array_sum($events['product_viewed']['daily']));
        $this->assertSame(1, $events['orderorbit:bundle_viewed']['total']);

        $byProduct = $explorer->breakdown($this->store, 'product_viewed', 'product_id', now()->subDays(30), now());
        $this->assertSame([['value' => '1', 'label' => 'Serum', 'total' => 2, 'visitors' => 2], ['value' => '2', 'label' => 'Cream', 'total' => 1, 'visitors' => 1]], $byProduct);
        $this->assertSame([], $explorer->breakdown($this->store, 'product_viewed', 'name; drop table', now()->subDays(30), now()));
    }

    public function test_funnels_follow_order_window_and_segments(): void
    {
        $steps = [['event' => 'product_viewed'], ['event' => 'product_added_to_cart'], ['event' => 'checkout_completed']];
        // a: completes in order. b: adds to cart before viewing (doesn't count for step 2), then views. c: views, buys 10 days later.
        $this->event('a', 'product_viewed', '-5 days 10:00', ['device' => 'mobile']);
        $this->event('a', 'product_added_to_cart', '-5 days 10:05');
        $this->event('a', 'checkout_completed', '-5 days 10:20');
        $this->event('b', 'product_added_to_cart', '-4 days 09:00');
        $this->event('b', 'product_viewed', '-4 days 09:10', ['device' => 'desktop']);
        $this->event('c', 'product_viewed', '-20 days', ['device' => 'desktop']);
        $this->event('c', 'product_added_to_cart', '-19 days');
        $this->event('c', 'checkout_completed', '-10 days');

        $funnels = app(Funnels::class);
        $report = $funnels->report($this->store, $steps, '7d', now()->subDays(30), now(), 'device');
        $this->assertSame([3, 2, 1], array_column($report['steps'], 'visitors'));
        $this->assertSame(43350, $report['steps'][1]['median_seconds'], 'a took 5 minutes and c a day: the median of two is their mean.');
        $this->assertEqualsWithDelta(50.0, $report['steps'][2]['from_previous'], 0.01);
        $this->assertSame(1, $report['steps'][2]['dropped']);
        $this->assertSame(['desktop' => [2, 1, 0], 'mobile' => [1, 1, 1]], $report['segments']);

        $this->assertSame([3, 2, 2], array_column($funnels->report($this->store, $steps, '30d', now()->subDays(30), now())['steps'], 'visitors'));
        $this->assertSame([3, 0, 0], array_column($funnels->report($this->store, [['event' => 'product_viewed'], ['event' => 'product_added_to_cart', 'experience' => 'nope'], ['event' => 'checkout_completed']], '30d', now()->subDays(30), now())['steps'], 'visitors'), 'A step for one experience only counts that experience.');
    }

    public function test_attribution_models_and_assisted_experiences(): void
    {
        // A visitor first came from Instagram, came back from Google and bought, after seeing a bundle.
        $this->event('v1', 'session_started', '-3 days', ['source' => 'instagram', 'session_id' => 's1']);
        $this->event('v1', 'orderorbit:bundle_viewed', '-3 days', ['experience_handle' => 'bnd1', 'session_id' => 's1']);
        $this->event('v1', 'session_started', '-1 day', ['source' => 'google', 'session_id' => 's2', 'campaign' => 'brand']);
        $this->event('v1', 'checkout_completed', '-1 day', ['source' => 'google', 'session_id' => 's2', 'campaign' => 'brand', 'value' => 100, 'order_ref' => 'o1', 'currency' => 'USD']);
        AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => 'attributed', 'name' => 'orderorbit:revenue_attributed', 'experience_handle' => 'bnd1', 'order_ref' => 'o1', 'value' => 40, 'visitor_id' => 'v1', 'occurred_at' => now()->subDay()]);
        // Another visitor bought directly without any experience.
        $this->event('v2', 'session_started', '-1 day', ['source' => 'direct', 'session_id' => 'x']);
        $this->event('v2', 'checkout_completed', '-1 day', ['source' => 'direct', 'session_id' => 'x', 'value' => 50, 'order_ref' => 'o2']);

        $attribution = app(Attribution::class);
        $last = $attribution->report($this->store, now()->subDays(30), now(), 7, 'last', ['bnd1' => 'stacked-cards']);
        $this->assertSame([150.0, 2, 3, 2], [$last['revenue'], $last['orders'], $last['sessions'], $last['visitors']]);
        $this->assertSame(['google' => ['orders' => 1, 'revenue' => 100.0], 'direct' => ['orders' => 1, 'revenue' => 50.0]], $last['by_source']);
        $this->assertSame(['brand' => ['orders' => 1, 'revenue' => 100.0]], $last['by_campaign']);
        $this->assertSame(['direct_orders' => 1, 'direct_revenue' => 40.0, 'assisted_orders' => 1, 'assisted_revenue' => 100.0, 'template' => 'stacked-cards'], $last['by_experience']['bnd1']);
        $this->assertSame([1, 100.0, 40.0, 75.0, 50.0], [$last['influenced_orders'], $last['influenced_revenue'], $last['direct_revenue'], $last['aov'], $last['per_session']]);
        $this->assertSame(['stacked-cards' => ['direct_revenue' => 40.0, 'assisted_revenue' => 100.0, 'experiences' => 1]], $last['by_template']);

        $first = $attribution->report($this->store, now()->subDays(30), now(), 7, 'first');
        $this->assertArrayHasKey('instagram', $first['by_source'], 'First touch credits the first visit in the window.');
        $short = $attribution->report($this->store, now()->subDays(30), now(), 1, 'first');
        $this->assertArrayHasKey('google', $short['by_source'], 'With a 1-day window the Instagram visit is too old.');
        $this->assertSame(0, $short['by_experience']['bnd1']['assisted_orders']);
    }

    public function test_journeys_screens_and_privacy(): void
    {
        $this->event('v1', 'page_viewed', '-10 days', ['page_type' => 'index', 'source' => 'google']);
        $this->event('v1', 'orderorbit:bundle_viewed', '-10 days', ['experience_handle' => 'bnd1']);
        $this->event('v1', 'checkout_completed', '-10 days', ['value' => 80, 'order_ref' => 'o1', 'customer_id' => '55', 'currency' => 'USD']);
        $this->event('v2', 'checkout_completed', '-2 days', ['value' => 30, 'order_ref' => 'o2', 'customer_id' => '55', 'session_id' => 'phone-1', 'currency' => 'USD']);
        AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => 'auto', 'name' => 'orderorbit:automation_triggered', 'customer_id' => '55', 'label' => 'Win-back', 'properties' => ['run_id' => 7], 'occurred_at' => now()->subDay()]);
        $this->event('v3', 'page_viewed', '-1 day');

        $journeys = app(Journeys::class);
        $this->assertSame(['v2', 'v1'], $journeys->list($this->store, now()->subDays(30), now())->pluck('visitor_id')->all());
        $this->assertCount(3, $journeys->list($this->store, now()->subDays(30), now(), false));

        $journey = $journeys->show($this->store, 'v1');
        $this->assertSame(['55', 2, 110.0], [$journey['customer'], $journey['orders'], $journey['revenue']]);
        $this->assertEqualsCanonicalizing(['v1', 'v2'], $journey['visitors'], 'The same customer on their phone.');
        $kinds = collect($journey['sessions'])->flatMap(fn ($s) => array_column($s['events'], 'kind'))->all();
        $this->assertSame(['browse', 'experience', 'purchase', 'purchase', 'automation'], $kinds);
        $this->assertSame(2, collect($journey['sessions'])->flatMap(fn ($s) => $s['events'])->max('order_number'));

        $owner = $this->member($this->store, 'owner');
        $people = collect($this->page('/app/analytics/journeys', $owner)->assertOk()->json('props.people.data'));
        $this->assertTrue($people->firstWhere('name', 'Customer 55')['repeat']);
        $this->page('/app/analytics/journeys/v1', $owner)->assertOk()->assertJsonPath('props.journey.customer', '55')->assertSee('Win-back')->assertSee('"run_id":7', false);
        $this->get('/app/analytics/journeys/nobody', $this->as($owner))->assertNotFound();

        // customers/redact removes the customer's events and the browsers they used.
        $this->assertSame(5, Journeys::forget($this->store, '55'));
        $this->assertSame(['v3'], AnalyticsEvent::pluck('visitor_id')->all());
    }

    public function test_report_screens_funnel_editing_and_plan_gating(): void
    {
        $owner = $this->member($this->store, 'owner');
        $this->event('a', 'product_viewed', '-1 day', ['product_id' => '1', 'label' => 'Serum']);
        $this->event('a', 'checkout_completed', '-1 day', ['value' => 20, 'order_ref' => 'o1', 'source' => 'direct']);

        $this->page('/app/analytics', $owner)->assertOk()->assertJsonPath('shared.nav.section', 'analytics')->assertJsonPath('shared.nav.groups.0.items.2.label', 'Funnels');
        $this->page('/app/analytics/events', $owner)->assertOk()->assertJsonPath('props.events.product_viewed.total', 1)
            ->assertJsonPath('props.catalogue.Shopify storefront events.payment_info_submitted', 'Payment info submitted')->assertSee('orderorbit:automation_completed');
        $this->page('/app/analytics/events?event=product_viewed&by=product_id', $owner)->assertOk()->assertJsonPath('props.breakdown.0.label', 'Serum');
        $this->page('/app/analytics/revenue?model=first&window=30', $owner)->assertOk()->assertJsonPath('props.model', 'first')->assertJsonPath('props.report.orders', 1);

        $this->post('/app/analytics/funnels', ['preset' => 'purchase'], $this->as($owner))->assertRedirectContains('/app/analytics/funnels/');
        $funnel = AnalyticsFunnel::sole();
        $this->assertSame(['product_viewed', 'product_added_to_cart', 'checkout_started', 'checkout_completed'], array_column($funnel->steps, 'event'));
        $this->page('/app/analytics/funnels/'.$funnel->id.'?compare=period', $owner)->assertOk()->assertJsonPath('props.report.steps.2.label', 'Checkout started')->assertJsonPath('props.previous.steps.0.visitors', 0);
        $this->page('/app/analytics/funnels/'.$funnel->id.'?compare=device', $owner)->assertOk();
        $this->page('/app/analytics/funnels', $owner)->assertOk()->assertJsonPath('props.funnels.0.steps', 'Product viewed → Product added to cart → Checkout started → Checkout completed (purchase)');

        $this->post('/app/analytics/funnels', ['name' => 'Too short', 'steps' => [['event' => 'product_viewed'], ['event' => 'bogus']]], $this->as($owner))->assertRedirectContains('error=');
        $this->post('/app/analytics/funnels/'.$funnel->id, ['name' => 'Bundle path', 'within' => 'session', 'steps' => [['event' => 'orderorbit:bundle_viewed', 'experience' => 'bnd1'], ['event' => 'checkout_completed'], ['event' => '']]], $this->as($owner))->assertRedirect();
        $funnel->refresh();
        $this->assertSame(['Bundle path', 'session', [['event' => 'orderorbit:bundle_viewed', 'experience' => 'bnd1'], ['event' => 'checkout_completed']]], [$funnel->name, $funnel->within, $funnel->steps]);
        $this->post('/app/analytics/funnels/'.$funnel->id.'/delete', [], $this->as($owner))->assertRedirect();
        $this->assertSame(0, AnalyticsFunnel::count());

        // Starter sees what the reports do, not the data.
        $this->store->forceFill(['plan' => 'starter'])->save();
        $this->page('/app/analytics/events', $owner)->assertOk()->assertJsonPath('props.locked', true)->assertJsonPath('props.events', [])->assertDontSee('Serum');
        $this->get('/app/analytics/journeys/a', $this->as($owner))->assertNotFound();
    }
}
