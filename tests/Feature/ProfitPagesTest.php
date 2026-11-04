<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\AdExpense;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitDaily;
use App\Models\Profit\ProfitSetting;
use App\Services\Profit\OrderSync;
use App\Services\Profit\ProfitEngine;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Phase 3 pages: access first, then the dashboard, costs, ads, breakdowns and ROAS read the same numbers. */
class ProfitPagesTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
    }

    private function order(string $day): array
    {
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v]];

        return ['id' => 501, 'name' => '#1', 'processed_at' => $day.'T10:00:00Z', 'source_name' => 'web', 'shipping_address' => ['country_code' => 'GB'],
            'total_shipping_price_set' => $m(0), 'total_tax_set' => $m(0), 'total_price_set' => $m(100),
            'line_items' => [['id' => 1, 'product_id' => 10, 'variant_id' => 101, 'title' => 'Serum', 'quantity' => 2, 'price_set' => $m(50), 'discount_allocations' => []]], 'refunds' => []];
    }

    public function test_profit_pages_from_access_to_numbers(): void
    {
        $store = $this->installedStore(['plan' => 'growth', 'timezone' => 'UTC', 'scopes' => config('shopify.scopes')]);
        $owner = $this->member($store, 'owner');
        $this->page('/app/profit', $owner)->assertNotFound();
        FeatureFlag::create(['key' => 'profit', 'mode' => 'all', 'enabled' => true]);
        Features::forget();

        // Access first: the approval screen lists what's missing.
        $this->page('/app/profit', $owner)->assertOk()->assertJsonPath('props.access.missing', ['read_inventory', 'read_all_orders'])
            ->assertJsonPath('shared.nav.label', 'Profit');

        $store->forceFill(['scopes' => config('shopify.scopes').',read_inventory,read_all_orders'])->save();
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now(), 'fees' => ['percent' => 0, 'fixed' => 0, 'countries' => []]])->save();
        ProductCost::create(['store_id' => $store->id, 'variant_id' => 101, 'product_id' => 10, 'title' => 'Serum']);
        $today = now()->toDateString();
        app(OrderSync::class)->fromWebhook($store, $this->order($today));
        app(ProfitEngine::class)->recalculateAll($store);

        $this->page('/app/profit', $owner)->assertOk()->assertJsonPath('props.access', null)
            ->assertJsonPath('props.totals.net_sales', 100)->assertJsonPath('props.totals.missing_cost_sales', 100)
            ->assertJsonPath('props.products.0.name', 'Serum');
        $this->page('/app/profit/costs', $owner)->assertOk()->assertJsonPath('props.missing.0.variant_id', 101)->assertJsonPath('props.missing.0.sales', 100);

        // A cost and a rule recalculate the numbers.
        $this->send('/app/profit/costs', ['costs' => [101 => 20]], $owner)->assertOk();
        $this->assertSame(40.0, ProfitDaily::where('store_id', $store->id)->value('cogs'));
        $this->send('/app/profit/costs/rules', ['fees' => ['percent' => 2, 'fixed' => 0], 'shipping' => ['mode' => 'per_order', 'value' => 5], 'fixed_costs' => [['name' => 'Apps', 'amount' => 30]]], $owner)->assertOk();
        $this->assertSame(53.0, ProfitDaily::where('store_id', $store->id)->value('contribution'), '100 − 40 cost − 2 fees − 5 shipping');

        // Ad spend tagged to the product.
        $this->send('/app/profit/ads', ['starts_on' => $today, 'ends_on' => $today, 'channel' => 'meta', 'amount' => 25, 'tags' => [['type' => 'product', 'id' => 'gid://shopify/Product/10', 'title' => 'Serum']]], $owner)->assertOk();
        $this->assertSame(1, AdExpense::count());
        $this->page('/app/profit/breakdowns?by=product', $owner)->assertOk()->assertJsonPath('props.rows.0.ad_spend', 25)->assertJsonPath('props.rows.0.after_ads', 28);
        $this->page('/app/profit/roas', $owner)->assertOk()->assertJsonPath('props.store.roas', 4)->assertJsonPath('props.products.0.poas', 2.12);
        $csv = $this->get('/app/profit/breakdowns/export?by=country', $this->as($owner))->assertOk()->streamedContent();
        $this->assertStringContainsString('GB,1,2,100', $csv);
    }

    public function test_free_sees_the_30_day_snapshot_and_previews_the_rest(): void
    {
        $store = $this->installedStore(['plan' => 'free', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        $owner = $this->member($store, 'owner');
        FeatureFlag::create(['key' => 'profit', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->page('/app/profit', $owner)->assertOk()->assertJsonPath('props.locked', null)->assertJsonPath('props.snapshot', true);
        $this->page('/app/profit/costs', $owner)->assertOk()->assertJsonPath('props.locked', 'Profit is in the Starter plan. Your draft is saved: upgrade to see it.');
        $this->send('/app/profit/costs', ['costs' => [1 => 5]], $owner)->assertForbidden();
    }
}
