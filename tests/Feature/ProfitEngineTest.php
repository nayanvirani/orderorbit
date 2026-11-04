<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\AdExpense;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitDaily;
use App\Models\Profit\ProfitSetting;
use App\Services\Profit\OrderSync;
use App\Services\Profit\ProfitEngine;
use App\Services\Profit\ProfitSync;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Phase 3: profit matches a manual calculation to the cent, with discounts, refunds, fees and ads. */
class ProfitEngineTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
    }

    /** 2 rings at 50 (10 discount, 1 refunded for 45) and a 10 gift box without a cost; 8 shipping, 5 tax. */
    private function order(): array
    {
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v, 'currency_code' => 'USD']];

        return [
            'id' => 9001, 'name' => '#1001', 'processed_at' => '2026-10-15T12:00:00Z', 'currency' => 'USD', 'source_name' => 'web',
            'shipping_address' => ['country_code' => 'US'], 'customer' => ['id' => 77, 'orders_count' => 2], 'payment_gateway_names' => ['shopify_payments'],
            'total_shipping_price_set' => $m(8), 'total_tax_set' => $m(5), 'total_price_set' => $m(113),
            'line_items' => [
                ['id' => 1, 'product_id' => 10, 'variant_id' => 101, 'title' => 'Ring', 'quantity' => 2, 'price_set' => $m(50), 'discount_allocations' => [['amount_set' => $m(10)]]],
                ['id' => 2, 'product_id' => 20, 'variant_id' => 201, 'title' => 'Gift box', 'quantity' => 1, 'price_set' => $m(10), 'discount_allocations' => []],
            ],
            'refunds' => [['refund_line_items' => [['line_item_id' => 1, 'quantity' => 1, 'subtotal_set' => $m(45)]], 'order_adjustments' => []]],
        ];
    }

    public function test_a_day_of_profit_to_the_cent(): void
    {
        $store = $this->installedStore(['timezone' => 'UTC']);
        ProductCost::create(['store_id' => $store->id, 'variant_id' => 101, 'product_id' => 10, 'shopify_cost' => 20]);
        ProductCost::create(['store_id' => $store->id, 'variant_id' => 201, 'product_id' => 20]); // no cost anywhere
        ProfitSetting::for($store->id)->update(['fees' => ['percent' => 2.9, 'fixed' => 0.30, 'countries' => []], 'shipping' => ['mode' => 'per_order', 'value' => 5], 'fixed_costs' => [['name' => 'Apps', 'amount' => 310]]]);
        AdExpense::create(['store_id' => $store->id, 'starts_on' => '2026-10-14', 'ends_on' => '2026-10-16', 'channel' => 'meta', 'amount' => 30]);

        app(OrderSync::class)->fromWebhook($store, $this->order());
        app(ProfitEngine::class)->recalculate($store, '2026-10-15', '2026-10-15');
        $day = ProfitDaily::where('store_id', $store->id)->sole();

        $this->assertSame(1, $day->orders);
        $this->assertSame(2, $day->units, '1 ring kept + 1 box');
        $this->assertSame(110.0, $day->gross);
        $this->assertSame(55.0, $day->net_sales, '110 − 10 discount − 45 refunded');
        $this->assertSame(20.0, $day->cogs, 'one ring kept at 20');
        $this->assertSame(10.0, $day->missing_cost_sales, 'the box has no cost');
        $this->assertSame(3.58, $day->fees, '2.9% of 113 + 0.30');
        $this->assertSame(5.0, $day->shipping_cost);
        $this->assertSame(35.0, $day->gross_profit);
        $this->assertSame(34.42, $day->contribution, '35 + 8 shipping − 3.577 fees − 5 shipping cost');
        $this->assertSame(10.0, $day->ad_spend, '30 over 3 days');
        $this->assertSame(10.0, $day->fixed_costs, '310 over 31 days of October');
        $this->assertSame(14.42, $day->profit);

        // A cost set in Growvia wins over Shopify's and recalculates history.
        ProductCost::where('variant_id', 101)->update(['cost' => 25, 'source' => 'manual']);
        app(ProfitEngine::class)->recalculate($store, '2026-10-15', '2026-10-15');
        $this->assertSame(25.0, ProfitDaily::sole()->cogs);

        // A cancelled order leaves the day.
        app(OrderSync::class)->fromWebhook($store, ['cancelled_at' => '2026-10-16T00:00:00Z'] + $this->order());
        app(ProfitEngine::class)->recalculate($store, '2026-10-15', '2026-10-15');
        $this->assertSame(0, ProfitDaily::sole()->orders, 'Ad spend and fixed costs stay on the day.');
    }

    public function test_the_sync_needs_the_flag_the_plan_and_the_access(): void
    {
        $store = $this->installedStore(['plan' => 'starter', 'scopes' => 'read_products,write_orders']);
        $this->assertFalse(ProfitSync::enabled($store));
        FeatureFlag::create(['key' => 'profit', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->assertTrue(ProfitSync::enabled($store->fresh()));
        $this->assertSame(['read_inventory', 'read_all_orders'], ProfitSync::missingAccess($store));
        $this->assertSame('skipped', app(ProfitSync::class)->run($store), 'Waits for the access.');
        $this->assertSame(365, ProfitSync::historyDays($store), 'Starter keeps 12 months.');

        $store->forceFill(['scopes' => 'read_products,write_orders,read_inventory,read_all_orders'])->save();
        Http::fake(fn () => Http::response(['data' => ['productVariants' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => false]], 'orders' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => false]]]]));
        $this->assertStringStartsWith('backfilled 0 orders', app(ProfitSync::class)->run($store->fresh()));
        $this->assertNotNull(ProfitSetting::for($store->id)->backfilled_at);
    }

    public function test_test_orders_count_on_development_stores_only(): void
    {
        $live = $this->installedStore(['timezone' => 'UTC']);
        $dev = $this->installedStore(['shop_domain' => 'dev.myshopify.com', 'timezone' => 'UTC', 'capabilities' => ['development_store' => true]]);
        $test = ['test' => true] + $this->order();
        $this->assertTrue(app(OrderSync::class)->fromWebhook($live, $test)->excluded, 'A live store leaves test orders out.');
        $this->assertFalse(app(OrderSync::class)->fromWebhook($dev, $test)->excluded, 'A development store counts them.');
    }
}
