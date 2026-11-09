<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\ProductCost;
use App\Models\Profit\ProfitSetting;
use App\Services\Profit\OrderSync;
use App\Services\Profit\ProfitEngine;
use App\Services\Profit\ProfitReports;
use App\Services\Questions\QuestionAnswerer;
use App\Services\Questions\QuestionLibrary;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Store questions (G18). */
class StoreQuestionsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private function store(string $plan = 'starter', string $domain = 'demo.myshopify.com')
    {
        $store = $this->installedStore(['shop_domain' => $domain, 'plan' => $plan, 'timezone' => 'UTC', 'currency' => 'USD', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now(), 'fees' => ['percent' => 0, 'fixed' => 0, 'countries' => []]])->save();
        ProductCost::create(['store_id' => $store->id, 'variant_id' => 101, 'product_id' => 10, 'title' => 'Serum', 'price' => 40, 'shopify_cost' => 10, 'seen_at' => now(), 'product_status' => 'ACTIVE', 'tracked' => true, 'available' => 3, 'has_image' => true, 'has_description' => true, 'sku' => 'S']);
        ProductCost::create(['store_id' => $store->id, 'variant_id' => 201, 'product_id' => 20, 'title' => 'Mask', 'price' => 20, 'seen_at' => now(), 'product_status' => 'ACTIVE', 'tracked' => true, 'available' => 50, 'has_image' => false, 'has_description' => true, 'sku' => 'M']);
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v]];
        for ($i = 0; $i < 10; $i++) {
            app(OrderSync::class)->fromWebhook($store, ['id' => 5000 + $i, 'processed_at' => now()->subDays($i)->toIso8601String(), 'customer' => ['id' => 70 + ($i % 4)], 'total_shipping_price_set' => $m(0), 'total_tax_set' => $m(0), 'total_price_set' => $m(60),
                'line_items' => [['id' => 1, 'product_id' => 10, 'variant_id' => 101, 'title' => 'Serum', 'quantity' => 1, 'price_set' => $m(40), 'discount_allocations' => []],
                    ['id' => 2, 'product_id' => 20, 'variant_id' => 201, 'title' => 'Mask', 'quantity' => 1, 'price_set' => $m(20), 'discount_allocations' => []]]]);
        }
        app(ProfitEngine::class)->recalculateAll($store);

        return $store;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        foreach (['profit', 'store_questions'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
    }

    public function test_the_library_has_over_a_thousand_questions_that_all_answer(): void
    {
        $library = app(QuestionLibrary::class)->all();
        $this->assertGreaterThan(1000, count($library));
        $this->assertSame('What were my net sales last month?', $library['value:net_sales:last_month']['text']);
        foreach (QuestionLibrary::STARTERS as $id) {
            $this->assertArrayHasKey($id, $library);
        }

        // Every question answers with a sentence (one store, all periods and kinds).
        $store = $this->store();
        $answerer = app(QuestionAnswerer::class);
        foreach ($library as $q) {
            $a = $answerer->answer($store, $q);
            $this->assertNotSame('', $a['sentence'], $q['id']);
        }
    }

    public function test_answers_match_the_dashboard_and_say_what_is_missing(): void
    {
        $store = $this->store();
        $answerer = app(QuestionAnswerer::class);
        $library = app(QuestionLibrary::class);
        [$from, $to] = $answerer->period($store, 'last_30');
        $t = app(ProfitReports::class)->totals($store, $from, $to);

        $a = $answerer->answer($store, $library->find('value:net_sales:last_30'));
        $this->assertEquals($t['net_sales'], $a['numbers'][0]['value']);
        $this->assertEquals(600, $t['net_sales']);
        $this->assertSame('Net sales in the last 30 days: USD 600.00.', $a['sentence']);

        $rank = $answerer->answer($store, $library->find('rank:product:top_sales:last_30'));
        $this->assertSame('Serum', $rank['table']['rows'][0][0]);

        $this->assertSame('ads', $answerer->answer($store, $library->find('value:ad_spend:last_30'))['needs']);
        $this->assertSame(['Mask'], array_column($answerer->answer($store, $library->find('data_image'))['table']['rows'], 0));
        // Serum: 3 left, 10 sold in 30 days, so about 9 days of stock.
        $this->assertNull($answerer->answer($store, $library->find('stock_out_7'))['table']);
        $this->assertSame(['Serum'], array_column($answerer->answer($store, $library->find('stock_out_14'))['table']['rows'], 0));
    }

    public function test_free_answers_ten_a_month_and_favourites_are_kept(): void
    {
        $store = $this->store('free');
        $owner = $this->member($store, 'owner');
        $this->page('/app/insights/questions', $owner)->assertOk()->assertJsonPath('props.remaining', 10);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/app/api/questions/ask', ['id' => 'value:orders:last_7'], $this->as($owner))->assertOk();
        }
        $this->postJson('/app/api/questions/ask', ['id' => 'value:orders:last_7'], $this->as($owner))->assertStatus(402)->assertJsonPath('remaining', 0);

        $this->postJson('/app/api/questions/favourite', ['id' => 'value:orders:last_7', 'on' => true], $this->as($owner))->assertOk();
        $this->page('/app/insights/questions', $owner)->assertJsonPath('props.favourites', ['value:orders:last_7'])->assertJsonPath('props.recent', ['value:orders:last_7']);

        $starter = $this->store('starter', 'starter.myshopify.com');
        $this->assertNull($starter->planLimit('store_questions'), 'Starter and up: every question.');
    }
}
