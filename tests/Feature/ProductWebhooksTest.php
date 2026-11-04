<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitSetting;
use App\Services\Profit\ProfitSync;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Product and inventory webhooks re-read that product at once; profit recalculates on the next run. */
class ProductWebhooksTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_a_cost_changed_in_shopify_arrives_by_webhook(): void
    {
        $this->setUpShopify();
        FeatureFlag::create(['key' => 'profit', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $store = $this->installedStore(['plan' => 'growth', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now(), 'costs_synced_at' => now()])->save();
        ProductCost::create(['store_id' => $store->id, 'variant_id' => 101, 'product_id' => 10, 'title' => 'Wax', 'price' => 24.95]);

        Http::fake(fn (Request $r) => Http::response(['data' => str_contains($r['query'] ?? '', 'inventoryItem(id')
            ? ['inventoryItem' => ['variant' => ['product' => ['id' => 'gid://shopify/Product/10']]]]
            : ['product' => ['variants' => ['nodes' => [['id' => 'gid://shopify/ProductVariant/101', 'displayName' => 'Wax', 'price' => '24.95', 'sku' => 'WAX', 'inventoryQuantity' => 7,
                'product' => ['id' => 'gid://shopify/Product/10', 'status' => 'ACTIVE', 'description' => 'Ski'], 'inventoryItem' => ['tracked' => true, 'unitCost' => ['amount' => '6.50']]]]]]]]));

        $sync = app(ProfitSync::class);
        $sync->productWebhook($store, 'inventory_items/update', ['id' => 555, 'cost' => '6.50']);
        $wax = ProductCost::where('variant_id', 101)->sole();
        $this->assertSame([6.5, 7, 'WAX', true], [$wax->shopify_cost, $wax->available, $wax->sku, $wax->tracked]);
        $this->assertSame('recalculated after product changes', $sync->run($store->fresh()));
        $this->assertSame('up to date', $sync->run($store->fresh()), 'Recalculated once.');

        $sync->productWebhook($store, 'products/delete', ['id' => 10]);
        $this->assertSame('DELETED', $wax->fresh()->product_status);
    }
}
