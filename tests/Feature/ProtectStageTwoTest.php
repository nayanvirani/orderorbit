<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitSetting;
use App\Models\Protect\HealthScore;
use App\Models\Protect\MonitoredUrl;
use App\Models\Protect\ProtectAlert;
use App\Models\Protect\UrlCheck;
use App\Services\Profit\OrderSync;
use App\Services\Profit\ProfitEngine;
use App\Services\Protect\Alerts;
use App\Services\Protect\Health;
use App\Services\Protect\UrlMonitor;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Phase 4, stage 2: URL monitor (G10), Health Score (G12) and Action Center (G13). */
class ProtectStageTwoTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        foreach (['profit', 'protect', 'protect_center', 'url_monitor'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
    }

    /** Serum (best seller, 4 left), Mask (sold below cost), Toner (no cost, no image, no SKU, sold out). */
    private function store(string $plan = 'growth')
    {
        $store = $this->installedStore(['plan' => $plan, 'timezone' => 'UTC', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now(), 'costs_synced_at' => now(), 'fees' => ['percent' => 0, 'fixed' => 0, 'countries' => []]])->save();
        $base = ['store_id' => $store->id, 'seen_at' => now(), 'product_status' => 'ACTIVE', 'tracked' => true, 'has_image' => true, 'has_description' => true, 'sku' => 'X', 'barcode' => '1', 'weight' => 1];
        ProductCost::create($base + ['variant_id' => 101, 'product_id' => 10, 'title' => 'Serum', 'price' => 40, 'shopify_cost' => 10, 'available' => 4]);
        ProductCost::create($base + ['variant_id' => 201, 'product_id' => 20, 'title' => 'Mask', 'price' => 20, 'shopify_cost' => 24, 'available' => 50]);
        ProductCost::create(['variant_id' => 301, 'product_id' => 30, 'title' => 'Toner', 'price' => 15, 'available' => 0, 'has_image' => false, 'sku' => null] + $base);
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v]];
        for ($i = 0; $i < 30; $i++) {
            app(OrderSync::class)->fromWebhook($store, ['id' => 1000 + $i, 'processed_at' => now()->subDays($i)->toIso8601String(), 'total_shipping_price_set' => $m(0), 'total_tax_set' => $m(0), 'total_price_set' => $m(75),
                'line_items' => [
                    ['id' => 1, 'product_id' => 10, 'variant_id' => 101, 'title' => 'Serum', 'quantity' => 1, 'price_set' => $m(40), 'discount_allocations' => []],
                    ['id' => 2, 'product_id' => 20, 'variant_id' => 201, 'title' => 'Mask', 'quantity' => 1, 'price_set' => $m(20), 'discount_allocations' => []],
                    ['id' => 3, 'product_id' => 30, 'variant_id' => 301, 'title' => 'Toner', 'quantity' => 1, 'price_set' => $m(15), 'discount_allocations' => []],
                ]]);
        }
        app(ProfitEngine::class)->recalculateAll($store);

        return $store;
    }

    public function test_action_center_lists_problems_and_suggestions(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        app(Alerts::class)->check($store);

        $ideas = ProtectAlert::where('severity', 'idea')->where('status', 'open')->pluck('rule')->sort()->values()->all();
        $this->assertSame(['idea_ad_spend', 'idea_costs', 'idea_first_offer', 'idea_product_data'], $ideas);
        $this->assertSame('Add costs to 1 product', ProtectAlert::where('rule', 'idea_costs')->sole()->title);

        $page = $this->page('/app/protect/actions', $owner)->assertOk();
        $items = $page->json('props.items');
        $this->assertSame('urgent', $items[0]['severity']);
        $this->assertSame('idea', end($items)['severity']);
        $this->assertGreaterThan(0, $page->json('props.atRisk'));

        // Suggestions stay off the Alerts page and out of the alert email count.
        $this->assertNotContains('idea_costs', array_column($this->page('/app/protect/alerts', $owner)->assertOk()->json('props.open'), 'rule'));

        // Done closes it; the cause is still there, so the next check brings it back.
        $idea = ProtectAlert::where('rule', 'idea_costs')->sole();
        $this->post('/app/protect/alerts/'.$idea->id, ['action' => 'done', 'from' => 'actions'], $this->as($owner))->assertRedirectContains('/app/protect/actions');
        $this->assertSame('closed', $idea->fresh()->status);
        app(Alerts::class)->check($store);
        $this->assertSame('open', $idea->fresh()->status);

        // Fixed: the cost is added, the suggestion closes itself.
        ProductCost::where('variant_id', 301)->update(['cost' => 5]);
        app(Alerts::class)->check($store);
        $this->assertSame('closed', $idea->fresh()->status);
    }

    public function test_health_score_leaves_out_what_cant_be_measured(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        app(Alerts::class)->check($store);

        $health = app(Health::class)->compute($store);
        $this->assertNull($health['categories']['pages'][0]); // no pages monitored yet: not scored
        $this->assertSame(67, $health['categories']['product_data'][0]); // 1 of 3 products with a gap
        $this->assertLessThan(100, $health['categories']['profit'][0]);
        $measured = array_filter(array_column($health['categories'], 0), fn ($v) => $v !== null);
        $this->assertSame((int) round(array_sum($measured) / count($measured)), $health['score']);

        app(Health::class)->record($store);
        $this->assertSame($health['score'], HealthScore::where('store_id', $store->id)->sole()->score);

        $page = $this->page('/app/protect/health', $owner)->assertOk();
        $this->assertSame($health['score'], $page->json('props.score'));
        $this->assertNotEmpty($page->json('props.ups'));
        $this->assertGreaterThanOrEqual($health['score'], $page->json('props.reach'));
    }

    public function test_pages_need_their_flag(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        FeatureFlag::whereIn('key', ['protect_center', 'url_monitor'])->delete();
        Features::forget();
        $this->page('/app/protect/actions', $owner)->assertNotFound();
        $this->page('/app/protect/health', $owner)->assertNotFound();
        $this->page('/app/protect/urls', $owner)->assertNotFound();
    }

    public function test_url_monitor_finds_pages_and_alerts_after_two_failures(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        Http::fake([
            '*/graphql.json' => Http::response(['data' => [
                'shop' => ['primaryDomain' => ['url' => 'https://glow.example']],
                'collections' => ['nodes' => [['title' => 'Sale', 'handle' => 'sale'], ['title' => 'Hidden', 'handle' => 'hidden']]],
                'nodes' => [['title' => 'Serum', 'onlineStoreUrl' => 'https://glow.example/products/serum']],
            ]]),
            'https://glow.example/collections/hidden' => Http::response('Not found', 404),
            'https://glow.example/pages/quiz' => Http::sequence()->push('ok', 200)->push('err', 500)->push('err', 500)->push('ok', 200),
            'https://glow.example/*' => Http::response('ok', 200),
        ]);
        $monitor = app(UrlMonitor::class);

        // Found pages that don't load (an unpublished collection) are never added.
        $this->assertSame(3, $monitor->discover($store));
        $this->assertSame(['https://glow.example/', 'https://glow.example/collections/sale', 'https://glow.example/products/serum'], MonitoredUrl::orderBy('url')->pluck('url')->all());

        // Only the store's own pages; the plan's limit counts.
        $this->assertNotNull($monitor->add($store, 'https://other.example/x'));
        $this->assertNull($monitor->add($store, '/pages/quiz'));
        $quiz = MonitoredUrl::where('url', 'https://glow.example/pages/quiz')->sole();
        $this->assertSame('up', $quiz->status);

        // One failure isn't down; two in a row are, and open an alert; the next good check closes it.
        $monitor->check($store, collect([$quiz]));
        $this->assertSame([1, 'up'], [$quiz->fresh()->fails, $quiz->fresh()->status]);
        $this->assertSame(0, ProtectAlert::where('rule', 'page_down')->count());
        $monitor->check($store, collect([$quiz->fresh()]));
        $this->assertSame('down', $quiz->fresh()->status);
        $alert = ProtectAlert::where('rule', 'page_down')->sole();
        $this->assertSame(['open', '/pages/quiz is down'], [$alert->status, $alert->title]);
        // The hourly alert check leaves the monitor's alerts alone.
        app(Alerts::class)->check($store);
        $this->assertSame('open', $alert->fresh()->status);
        $monitor->check($store, collect([$quiz->fresh()]));
        $this->assertSame(['up', 'closed'], [$quiz->fresh()->status, $alert->fresh()->status]);
        $this->assertSame(4, UrlCheck::where('monitored_url_id', $quiz->id)->count());

        $page = $this->page('/app/protect/urls', $owner)->assertOk();
        $this->assertSame(4, $page->json('props.used'));
        $this->assertSame(60, $page->json('props.interval')); // Growth: hourly
        $row = collect($page->json('props.rows'))->firstWhere('path', '/pages/quiz');
        $this->assertSame(['up', 50.0], [$row['status'], (float) $row['uptime']]);
        $this->assertSame('down', $row['strip'][23]);

        // Removing a found page keeps it off; the merchant's own page is deleted.
        $home = MonitoredUrl::where('kind', 'home')->sole();
        $this->post('/app/protect/urls/'.$home->id.'/remove', [], $this->as($owner))->assertRedirect();
        $this->assertTrue($home->fresh()->removed);
        $monitor->discover($store);
        $this->assertTrue($home->fresh()->removed);
        $this->post('/app/protect/urls/'.$quiz->id.'/remove', [], $this->as($owner))->assertRedirect();
        $this->assertNull($quiz->fresh());
    }

    public function test_password_page_isnt_down_and_plans_set_the_pace(): void
    {
        $store = $this->store();
        Http::fake(['https://glow.example/*' => Http::response('', 302, ['Location' => 'https://glow.example/password'])]);
        $url = MonitoredUrl::create(['store_id' => $store->id, 'url' => 'https://glow.example/', 'kind' => 'home']);
        $monitor = app(UrlMonitor::class);
        $monitor->check($store, collect([$url]));
        $monitor->check($store, collect([$url->fresh()]));
        $this->assertSame(['locked', 0], [$url->fresh()->status, $url->fresh()->fails]);
        $this->assertSame(0, ProtectAlert::where('rule', 'page_down')->count());

        $this->assertSame(0, $monitor->limit($this->installedStore(['shop_domain' => 's.myshopify.com', 'plan' => 'starter'])));
        $this->assertSame(15, $monitor->interval($this->installedStore(['shop_domain' => 'sc.myshopify.com', 'plan' => 'scale'])));
    }
}
