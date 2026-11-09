<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\ProfitSetting;
use App\Services\Insights\CustomerValue;
use App\Services\Profit\OrderSync;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Customer value insights (G19). */
class CustomerValueTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_repeat_rate_second_order_value_cohorts_and_best_first_products(): void
    {
        $this->setUpShopify();
        FeatureFlag::create(['key' => 'profit', 'mode' => 'all', 'enabled' => true]);
        FeatureFlag::create(['key' => 'customer_value', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->travelTo(now()->setDate(2026, 11, 9));
        $store = $this->installedStore(['plan' => 'scale', 'timezone' => 'UTC', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now()])->save();
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v]];
        $order = function (int $id, int $customer, string $day, int $product) use ($store, $m) {
            app(OrderSync::class)->fromWebhook($store, ['id' => $id, 'processed_at' => $day.'T12:00:00Z', 'customer' => ['id' => $customer], 'total_shipping_price_set' => $m(0), 'total_tax_set' => $m(0), 'total_price_set' => $m(50),
                'line_items' => [['id' => $id, 'product_id' => $product, 'variant_id' => $product * 10, 'title' => $product === 1 ? 'Night Cream' : 'Clay Mask', 'quantity' => 1, 'price_set' => $m(50), 'discount_allocations' => []]]]);
        };
        // 6 Night Cream first buyers in August, 3 come back in September; 5 Clay Mask first buyers, none back.
        for ($c = 1; $c <= 6; $c++) {
            $order(100 + $c, $c, '2026-08-05', 1);
            if ($c <= 3) {
                $order(200 + $c, $c, '2026-09-04', 1);
            }
        }
        for ($c = 11; $c <= 15; $c++) {
            $order(300 + $c, $c, '2026-08-10', 2);
        }

        $r = app(CustomerValue::class)->build($store);
        $this->assertSame(11, $r['summary']['customers']);
        $this->assertSame(27, $r['summary']['repeat_rate']); // 3 of 11
        $this->assertSame(30, $r['summary']['second_order_days']);
        $aug = collect($r['cohorts'])->firstWhere('month', 'Aug 2026');
        $this->assertSame(11, $aug['customers']);
        $this->assertSame([27, 0, null, null, null, null], $aug['cells']); // September 3/11; October 0; November not finished
        $this->assertSame(['Night Cream', 50], [$r['best'][0]['name'], $r['best'][0]['return_rate']]);
        $this->assertEquals(75, $r['best'][0]['value']); // 9 orders × 50 / 6 buyers
        $this->assertSame(0, $r['best'][1]['return_rate']);

        $owner = $this->member($store, 'owner');
        $this->page('/app/insights/customers', $owner)->assertOk()->assertJsonPath('props.summary.customers', 11);
    }
}
