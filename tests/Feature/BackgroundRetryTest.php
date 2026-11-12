<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\ProfitSetting;
use App\Services\Profit\ProfitSync;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** When Shopify refuses (e.g. an IP allowlist), background jobs rest instead of retrying every 5 minutes. */
class BackgroundRetryTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public function test_profit_sync_rests_after_a_failure_then_tries_again(): void
    {
        $this->setUpShopify();
        FeatureFlag::create(['key' => 'profit', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $store = $this->installedStore(['plan' => 'growth', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now(), 'costs_synced_at' => now()->subDay()])->save();
        Http::fake(['*' => Http::response(['errors' => "[API] This app's IP restrictions don't allow requests from 52.8.59.65."], 403)]);

        $this->assertStringStartsWith('failed (next try', app(ProfitSync::class)->run($store));
        $calls = count(Http::recorded());
        $this->assertGreaterThan(0, $calls);

        // Five minutes later: nothing is sent to Shopify.
        $this->travel(5)->minutes();
        $this->assertStringStartsWith('paused after an error', app(ProfitSync::class)->run($store));
        $this->assertCount($calls, Http::recorded());

        // After the hour it tries again; the second failure rests 2 hours.
        $this->travel(61)->minutes();
        $this->assertStringStartsWith('failed', app(ProfitSync::class)->run($store));
        $this->assertGreaterThan($calls, count(Http::recorded()));
        $this->travel(90)->minutes();
        $this->assertStringStartsWith('paused', app(ProfitSync::class)->run($store));
    }
}
