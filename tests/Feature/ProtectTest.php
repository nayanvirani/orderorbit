<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitSetting;
use App\Models\Protect\ProtectAlert;
use App\Services\Profit\OrderSync;
use App\Services\Profit\ProfitEngine;
use App\Services\Protect\Alerts;
use App\Services\Protect\Protect;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Phase 4, stage 1: money-leak alerts, days of stock and product data from the synced data. */
class ProtectTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        foreach (['profit', 'protect'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
    }

    /** Serum (best seller, 4 left, selling 1 a day), a Mask sold below cost, a Toner with no cost and gaps. */
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

    public function test_alerts_open_close_and_follow_the_plan(): void
    {
        $store = $this->store();
        $r = app(Alerts::class)->check($store);
        $rules = ProtectAlert::where('status', 'open')->where('severity', '!=', 'idea')->pluck('rule')->sort()->values()->all();
        $this->assertSame(['below_cost', 'low_stock', 'missing_cost', 'negative_margin', 'sold_out'], $rules);
        $this->assertSame(5, $r['opened']);
        $this->assertStringContainsString('4 left', ProtectAlert::where('rule', 'low_stock')->sole()->what);

        // Fixed: restocked and costed; snoozed stays snoozed while it's still true.
        ProtectAlert::where('rule', 'below_cost')->update(['status' => 'snoozed', 'snoozed_until' => now()->addDays(7)]);
        ProductCost::where('variant_id', 101)->update(['available' => 100]);
        ProductCost::where('variant_id', 301)->update(['cost' => 5]);
        app(ProfitEngine::class)->recalculateAll($store);
        app(Alerts::class)->check($store);
        $this->assertSame('closed', ProtectAlert::where('rule', 'low_stock')->sole()->status);
        $this->assertSame('closed', ProtectAlert::where('rule', 'missing_cost')->sole()->status);
        $this->assertSame('snoozed', ProtectAlert::where('rule', 'below_cost')->sole()->status);

        // Free has three rules only.
        $free = $this->installedStore(['shop_domain' => 'free.myshopify.com', 'plan' => 'free']);
        $this->assertSame(['price_mistake', 'low_stock', 'missing_cost'], app(Alerts::class)->activeRules($free));
    }

    public function test_inventory_and_product_data(): void
    {
        $store = $this->store();
        $rows = collect(app(Protect::class)->inventory($store))->keyBy('variant_id');
        $this->assertSame(['reorder', 1.0, 4, 55], [$rows[101]['status'], $rows[101]['rate'], $rows[101]['days'], $rows[101]['qty']], '1 a day: 45 cover + 14 lead days = 59, minus 4 available');
        $this->assertSame('sold_out', $rows[301]['status']);
        $this->assertSame('healthy', $rows[201]['status']);

        $products = collect(app(Protect::class)->productData($store))->keyBy('product_id');
        $this->assertSame(['image' => 'No image', 'sku' => 'No SKU', 'cost' => 'No cost'], $products[30]['issues']);
        $this->assertArrayNotHasKey(10, $products->all(), 'Serum has everything.');

        $owner = $this->member($store, 'owner');
        app(Alerts::class)->check($store);
        $this->page('/app/protect/alerts', $owner)->assertOk()->assertJsonPath('component', 'protect/alerts')->assertJsonPath('shared.nav.label', 'Protect');
        $this->page('/app/protect/inventory', $owner)->assertOk()->assertJsonPath('props.summary.sold_out', 1);
        $this->page('/app/protect/products', $owner)->assertOk()->assertJsonPath('props.rows.0.product_id', 30);
        $alert = ProtectAlert::where('rule', 'sold_out')->sole();
        $this->send("/app/protect/alerts/{$alert->id}", ['action' => 'mute'], $owner)->assertOk();
        $this->assertSame('muted', $alert->fresh()->status);
        $csv = $this->get('/app/protect/inventory/reorder', $this->as($owner))->assertOk()->streamedContent();
        $this->assertStringContainsString('Serum', $csv);

        // Check now: re-reads Shopify, at most once every 5 minutes.
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response(['data' => ['productVariants' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => false]]]]));
        $this->send('/app/protect/refresh', ['from' => 'products'], $owner)->assertOk();
        $this->send('/app/protect/refresh', ['from' => 'products'], $owner)->assertStatus(422);
    }
}
