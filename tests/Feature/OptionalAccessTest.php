<?php

namespace Tests\Feature;

use App\Services\Insights\StoreTraffic;
use App\Support\OptionalAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Settings › Optional access and store sessions from Shopify's reports (read_reports). */
class OptionalAccessTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_settings_list_each_optional_access_and_whether_it_is_on(): void
    {
        $this->setUpShopify();
        $store = $this->installedStore(['scopes' => config('shopify.scopes').',read_inventory,read_reports']);
        $owner = $this->member($store, 'owner');
        $list = collect($this->page('/app/settings/store', $owner)->assertOk()->json('props.optional'))->keyBy('scope');
        $this->assertSame(['read_inventory', 'read_all_orders', 'read_locations', 'read_locales', 'read_reports'], $list->keys()->all());
        $this->assertTrue($list['read_inventory']['on']);
        $this->assertFalse($list['read_all_orders']['on']);
        $this->assertTrue(OptionalAccess::granted($store, 'read_reports'));
    }

    public function test_store_traffic_reads_either_row_shape_and_ignores_the_unexpected(): void
    {
        $cols = ['columns' => [['name' => 'sessions'], ['name' => 'conversion_rate']]];
        $this->assertSame(['sessions' => 1200, 'conversion' => 2.5], StoreTraffic::parse(['tableData' => $cols + ['rows' => [['sessions' => 1200, 'conversion_rate' => 0.025]]], 'parseErrors' => []]));
        $this->assertSame(['sessions' => 900, 'conversion' => 3.1], StoreTraffic::parse(['tableData' => $cols + ['rows' => [[900, 3.1]]], 'parseErrors' => []]));
        $this->assertNull(StoreTraffic::parse(['tableData' => $cols + ['rows' => []], 'parseErrors' => []]));
        $this->assertNull(StoreTraffic::parse(['tableData' => $cols + ['rows' => [['sessions' => 1]]], 'parseErrors' => ['bad query']]));
        $this->assertNull(StoreTraffic::parse(null));
    }
}
