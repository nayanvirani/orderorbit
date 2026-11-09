<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Profit\ProfitDaily;
use App\Models\Profit\ProfitSetting;
use App\Models\Store;
use App\Services\Ai\Ai;
use App\Services\Ai\OfferIdeas;
use App\Services\Insights\MorningBrief;
use App\Services\Profit\OrderSync;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** AI Morning Brief (G17) and AI offer ideas (G20), with the AI replaced by a stand-in. */
class AiFeaturesTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    public array $prompts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        foreach (['profit', 'morning_brief', 'ai_offer_ideas'] as $flag) {
            FeatureFlag::create(['key' => $flag, 'mode' => 'all', 'enabled' => true]);
        }
        Features::forget();
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(8, 0)); // a Monday
        $test = $this;
        $this->app->instance(Ai::class, new class($test) extends Ai
        {
            public function __construct(private $test) {}

            public function configured(): bool { return true; }

            public function hasCapacity(): bool { return true; }

            public function json(Store $store, string $feature, string $system, string $prompt, array $schema, int $maxTokens = 4000): ?array
            {
                if (! $this->available($store)) {
                    return null;
                }
                $this->test->prompts[] = $prompt;
                DB::table('ai_usage')->insert(['store_id' => $store->id, 'feature' => $feature, 'model' => 'stand-in', 'input_tokens' => 100, 'output_tokens' => 50, 'cost' => 0.001, 'outcome' => 'ok', 'created_at' => now()]);

                return $feature === 'morning_brief'
                    ? ['lines' => [['text' => 'Sales were well above a usual Sunday.', 'facts' => ['net_sales']], ['text' => 'Made-up line.', 'facts' => ['nonsense']]]]
                    : ['options' => [['headline' => 'Better together', 'subheadline' => 'Night Cream and Serum in one bundle.', 'cta' => 'Add both']]];
            }
        });
    }

    private function store(string $plan = 'premium'): Store
    {
        $store = $this->installedStore(['plan' => $plan, 'timezone' => 'UTC', 'currency' => 'USD', 'name' => 'Glowlab', 'scopes' => config('shopify.scopes').',read_inventory,read_all_orders']);
        ProfitSetting::for($store->id)->forceFill(['backfilled_at' => now()])->save();

        return $store;
    }

    public function test_the_brief_cites_facts_and_is_quiet_when_nothing_changed(): void
    {
        $store = $this->store();
        // Usual Sundays: 1,000; yesterday (Sunday): 1,500 → +50%, notable.
        foreach ([1, 2, 3, 4] as $w) {
            ProfitDaily::create(['store_id' => $store->id, 'day' => now()->subDay()->subWeeks($w)->toDateString(), 'orders' => 10, 'net_sales' => 1000, 'contribution' => 300]);
        }
        ProfitDaily::create(['store_id' => $store->id, 'day' => now()->subDay()->toDateString(), 'orders' => 15, 'net_sales' => 1500, 'contribution' => 450]);

        $facts = app(MorningBrief::class)->facts($store);
        $this->assertTrue($facts['net_sales']['notable']);
        $this->assertStringContainsString('+50%', $facts['net_sales']['value']);

        $this->assertSame('ready', app(MorningBrief::class)->make($store));
        $brief = DB::table('morning_briefs')->sole();
        $this->assertSame([['text' => 'Sales were well above a usual Sunday.', 'facts' => ['net_sales']]], json_decode($brief->lines, true), 'Lines citing no real fact are dropped.');
        $this->assertStringNotContainsString('@', implode(' ', $this->prompts), 'No customer details in prompts.');
        $this->assertNull(app(MorningBrief::class)->make($store), 'Once a day.');

        // A store where nothing changed gets no brief and no AI call.
        $quiet = $this->installedStore(['shop_domain' => 'quiet.myshopify.com', 'plan' => 'premium', 'timezone' => 'UTC']);
        $calls = count($this->prompts);
        $this->assertSame('quiet', app(MorningBrief::class)->make($quiet));
        $this->assertSame($calls, count($this->prompts));

        $this->page('/app/insights/brief', $this->member($store, 'owner'))->assertOk()->assertJsonPath('props.briefs.0.status', 'ready');
    }

    public function test_ai_pauses_when_the_monthly_budget_is_used(): void
    {
        $store = $this->store();
        DB::table('ai_usage')->insert(['store_id' => $store->id, 'feature' => 'x', 'model' => 'm', 'input_tokens' => 0, 'output_tokens' => 0, 'cost' => 5, 'outcome' => 'ok', 'created_at' => now()]);
        $this->assertFalse(app(Ai::class)->available($store));
        $this->assertSame(0.0, app(Ai::class)->budget($this->installedStore(['shop_domain' => 's.myshopify.com', 'plan' => 'scale'])), 'No AI below Premium.');
    }

    public function test_offer_ideas_pair_products_bought_together_and_suggest_text(): void
    {
        $store = $this->store();
        $m = fn ($v) => ['shop_money' => ['amount' => (string) $v]];
        foreach (range(1, 4) as $i) {
            app(OrderSync::class)->fromWebhook($store, ['id' => 100 + $i, 'processed_at' => now()->subDays($i)->toIso8601String(), 'total_shipping_price_set' => $m(0), 'total_tax_set' => $m(0), 'total_price_set' => $m(70),
                'line_items' => [['id' => 1, 'product_id' => 1, 'variant_id' => 10, 'title' => 'Night Cream', 'quantity' => 1, 'price_set' => $m(40), 'discount_allocations' => []],
                    ['id' => 2, 'product_id' => 2, 'variant_id' => 20, 'title' => 'Glow Serum', 'quantity' => 1, 'price_set' => $m(30), 'discount_allocations' => []]]]);
        }
        $pairs = app(OfferIdeas::class)->pairs($store);
        $this->assertSame(['Night Cream', 'Glow Serum', 4, 100], [$pairs[0]['a']['name'], $pairs[0]['b']['name'], $pairs[0]['orders'], $pairs[0]['share']]);

        $owner = $this->member($store, 'owner');
        $this->postJson('/app/api/ai/copy', ['type' => 'bundles', 'products' => ['Night Cream', 'Glow Serum']], $this->as($owner))
            ->assertOk()->assertJsonPath('options.0.headline', 'Better together')->assertJsonPath('source', 'ai');
    }

    public function test_without_ai_growvia_writes_the_brief_and_offer_text(): void
    {
        $this->app->forgetInstance(Ai::class);
        $this->app->offsetUnset(Ai::class);
        config(['services.anthropic.key' => null]);
        $store = $this->store();
        foreach ([1, 2, 3, 4] as $w) {
            ProfitDaily::create(['store_id' => $store->id, 'day' => now()->subDay()->subWeeks($w)->toDateString(), 'orders' => 10, 'net_sales' => 1000, 'contribution' => 300]);
        }
        ProfitDaily::create(['store_id' => $store->id, 'day' => now()->subDay()->toDateString(), 'orders' => 6, 'net_sales' => 600, 'contribution' => 180]);

        $this->assertSame('ready', app(MorningBrief::class)->make($store));
        $brief = DB::table('morning_briefs')->sole();
        $this->assertSame('auto', $brief->source);
        $lines = json_decode($brief->lines, true);
        $this->assertSame('Net sales yesterday were USD 600.00, down 40% on a usual Sunday (USD 1,000.00).', $lines[0]['text']);
        $this->assertSame(['net_sales'], $lines[0]['facts']);
        $this->assertSame('You had 6 orders yesterday, down 40% on a usual Sunday.', $lines[1]['text']);
        $this->assertStringStartsWith('Today, check that your best sellers are in stock', end($lines)['text'], 'The one thing to do comes last.');
        $this->assertLessThanOrEqual(5, count($lines));
        $this->assertSame([], $this->prompts, 'No AI call.');
        $this->page('/app/insights/brief', $this->member($store, 'owner'))->assertOk()->assertJsonPath('props.briefs.0.source', 'auto')->assertJsonPath('props.ai', false);

        $owner = $this->member($store, 'owner');
        $r = $this->postJson('/app/api/ai/copy', ['type' => 'bundles', 'products' => ['Night Cream', 'Glow Serum'], 'discount' => '10%'], $this->as($owner))
            ->assertOk()->assertJsonPath('source', 'auto')->assertJsonCount(3, 'options');
        $this->assertSame(['headline' => 'Save 10% on the set', 'subheadline' => 'Get Night Cream and Glow Serum together.', 'cta' => 'Add both'], $r->json('options.0'));
        $this->postJson('/app/api/ai/copy', ['type' => 'upsell'], $this->as($owner))->assertOk()->assertJsonPath('options.0.headline', 'Something to add?');
        foreach (OfferIdeas::auto(['products' => [str_repeat('Very long product name ', 6), 'B', 'C']]) as $o) {
            $this->assertLessThanOrEqual(60, mb_strlen($o['headline']));
            $this->assertLessThanOrEqual(120, mb_strlen($o['subheadline']));
            $this->assertLessThanOrEqual(24, mb_strlen($o['cta']));
        }
    }
}
