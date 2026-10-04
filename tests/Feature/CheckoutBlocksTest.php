<?php

namespace Tests\Feature;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Models\AnalyticsEvent;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class CheckoutBlocksTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(fn (Request $request) => match (true) {
            str_contains($request['query'] ?? '', 'currentAppInstallation { id } shop { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => 'gid://shopify/Shop/7']]]),
            str_contains($request['query'] ?? '', 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
            default => Http::response(['data' => []]),
        });
    }

    private function ready(ExperienceManager $manager, $store, string $type, string $template, array $content = [])
    {
        $experience = $manager->create($store, $type, $template, null);
        $config = $experience->draft_config;
        $config['content'] = array_merge($config['content'], $content);
        $manager->saveDraft($experience, Schema::normalize($type, $config)[0], [], null);

        return $experience->fresh();
    }

    public function test_thirteen_block_types_with_checkout_only_settings(): void
    {
        $checkout = array_keys(array_filter(Registry::types(), fn ($t) => $t['surface'] === 'checkout'));
        $thankYou = array_keys(array_filter(Registry::types(), fn ($t) => $t['surface'] === 'thank-you'));
        $this->assertCount(6, $checkout);
        $this->assertCount(7, $thankYou);

        // Checkout uses the store's checkout branding: no design settings, and only the targeting checkout knows.
        $fields = Schema::fields('checkout-trust');
        $this->assertSame([], $fields['design']);
        $this->assertSame(['priority'], array_keys($fields['behavior']));
        $this->assertSame(['cart_min', 'cart_max', 'countries'], array_keys($fields['targeting']));

        // Merchant-only content is required: real reviews, real codes, a real deadline.
        $this->assertArrayHasKey('content.reviews', Schema::normalize('checkout-reviews', Schema::defaults('checkout-reviews'))[1]);
        $this->assertArrayHasKey('content.code', Schema::normalize('ty-discount', Schema::defaults('ty-discount'))[1]);
        $this->assertArrayHasKey('content.ends_at', Schema::normalize('checkout-countdown', Schema::defaults('checkout-countdown'))[1]);
        $this->assertSame([], Schema::normalize('ty-survey', Schema::defaults('ty-survey'))[1], 'A survey works out of the box.');
    }

    public function test_publishing_follows_plan_and_shopify_capabilities(): void
    {
        $manager = app(ExperienceManager::class);

        // Starter doesn't include checkout blocks.
        $starter = $this->installedStore(['plan' => 'starter', 'capabilities' => ['checkout_blocks' => true]]);
        try {
            $manager->publish($this->ready($manager, $starter, 'ty-survey', 'choice-list'), null);
            $this->fail('Checkout blocks need Growth or Scale.');
        } catch (PublishException $e) {
            $this->assertSame('plan', $e->reason);
        }

        // Growth without Shopify Plus: Thank You blocks yes, in-checkout blocks no.
        $growth = $this->installedStore(['shop_domain' => 'growth.myshopify.com', 'plan' => 'growth', 'capabilities' => ['checkout_blocks' => false]]);
        $manager->publish($this->ready($manager, $growth, 'ty-survey', 'choice-list'), null);
        try {
            $manager->publish($this->ready($manager, $growth, 'checkout-trust', 'trust-row'), null);
            $this->fail('In-checkout blocks need Shopify Plus.');
        } catch (PublishException $e) {
            $this->assertStringContainsString('Shopify Plus', $e->getMessage());
        }

        // Shipping progress and free gifts follow a live Progressive gifts campaign.
        $plus = $this->installedStore(['shop_domain' => 'plus.myshopify.com', 'plan' => 'growth', 'capabilities' => ['checkout_blocks' => true]]);
        $manager->publish($this->ready($manager, $plus, 'checkout-trust', 'trust-row'), null);
        try {
            $manager->publish($this->ready($manager, $plus, 'checkout-shipping', 'single-threshold'), null);
            $this->fail('Needs a Progressive gifts campaign.');
        } catch (PublishException $e) {
            $this->assertStringContainsString('Progressive gifts', $e->getMessage());
        }
    }

    public function test_checkout_blocks_are_published_to_the_shop_for_the_extension(): void
    {
        $manager = app(ExperienceManager::class);
        $store = $this->installedStore(['plan' => 'scale', 'capabilities' => ['checkout_blocks' => true]]);
        $manager->publish($manager->create($store, 'trust', 'trust-row', null), null);
        $manager->publish($this->ready($manager, $store, 'checkout-promo', 'offer-banner', ['headline' => 'Spend $50, get free shipping', 'code' => 'SHIPFREE']), null);
        $manager->publish($this->ready($manager, $store, 'ty-discount', 'code-card', ['code' => 'THANKS10']), null);

        $publisher = app(StorefrontPublisher::class);
        $this->assertSame(['trust'], array_column($publisher->payload($store)['experiences'], 'type'), 'The theme only gets storefront blocks.');

        $checkout = $publisher->checkoutPayload($store);
        $this->assertEqualsCanonicalizing(['checkout-promo', 'ty-discount'], array_column($checkout['experiences'], 'type'));
        $promo = collect($checkout['experiences'])->firstWhere('type', 'checkout-promo');
        $this->assertSame(['banner', 'SHIPFREE'], [$promo['style'], $promo['content']['code']]);
        $this->assertNull($checkout['gifts']);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request['query'] ?? '', 'metafieldsSet')) {
                return false;
            }
            $fields = json_decode($request->body(), true)['variables']['metafields'];
            $shop = collect($fields)->firstWhere('key', 'checkout');

            return $fields[0]['key'] === 'experiences' && $shop && $shop['ownerId'] === 'gid://shopify/Shop/7' && $shop['namespace'] === '$app'
                && collect(json_decode($shop['value'], true)['experiences'])->pluck('type')->contains('ty-discount');
        });
    }

    public function test_survey_answers_reach_the_app(): void
    {
        $store = $this->installedStore(['plan' => 'growth', 'pixel_token' => str_repeat('s', 40)]);
        $owner = $this->member($store, 'owner');
        $manager = app(ExperienceManager::class);
        $survey = $this->ready($manager, $store, 'ty-survey', 'choice-list');
        $manager->publish($survey, null);

        $send = fn (string $answer) => $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode([
            't' => str_repeat('s', 40), 's' => $store->shop_domain, 'k' => 'e', 'e' => 'orderorbit:survey_answered', 'x' => $survey->handle, 'a' => $answer,
        ]));
        $send('Instagram');
        $send('Instagram');
        $send('<b>A friend</b>');

        $this->assertSame(['A friend', 'Instagram', 'Instagram'], AnalyticsEvent::where('event', 'survey')->orderBy('label')->pluck('label')->all());
        $this->get('/app/cro/experiences/'.$survey->id, $this->as($owner))->assertOk()
            ->assertSee('Answers · last 30 days')->assertSee('Instagram')->assertSee('67%');
    }

    public function test_app_screens_explain_where_blocks_go(): void
    {
        $owner = $this->member($plus = $this->installedStore(['plan' => 'growth', 'capabilities' => ['checkout_blocks' => true]]), 'owner');
        $this->get('/app/cro/features/checkout', $this->as($owner))->assertOk()
            ->assertSee('Create checkout reviews block')->assertSee('Open checkout editor')->assertSee('settings/checkout/editor', false);
        $this->get('/app/cro/features/thank-you', $this->as($owner))->assertOk()->assertSee('Create survey')->assertSee('page=thank-you', false);
        $this->get('/app/cro', $this->as($owner))->assertOk()->assertSee('Checkout blocks')->assertSee('Thank You &amp; Order Status', false);

        $this->post('/app/cro/experiences', ['type' => 'checkout-gift', 'template' => 'reward-card'], $this->as($owner))->assertRedirectContains('/edit');
        $this->get('/app/cro/experiences/1/edit', $this->as($owner))->assertOk()
            ->assertSee('Checkout blocks use your checkout\'s own fonts and colours', false)->assertSee('checkout-gift')->assertSee('Claim button');

        // Without Shopify Plus the in-checkout blocks aren't offered; without Growth, publishing is explained.
        $plus->forceFill(['plan' => 'starter', 'capabilities' => ['checkout_blocks' => false]])->save();
        $this->get('/app/cro/features/checkout', $this->as($owner))->assertOk()
            ->assertSee('Blocks inside checkout need Shopify Plus')->assertDontSee('Create checkout reviews block');
        $this->get('/app/cro/features/thank-you', $this->as($owner))->assertOk()->assertSee('On the Growth plan and above')->assertSee('Create survey');
    }

    public function test_older_blocks_pick_up_new_settings_and_storefront_countdown_has_three_types(): void
    {
        $owner = $this->member($store = $this->installedStore(['plan' => 'growth', 'capabilities' => ['checkout_blocks' => true]]), 'owner');
        $experience = app(ExperienceManager::class)->create($store, 'checkout-countdown', 'compact', null);
        // Saved before timer types existed.
        $config = $experience->draft_config;
        unset($config['content']['mode'], $config['content']['hours'], $config['content']['minutes'], $config['content']['repeat']);
        $experience->forceFill(['draft_config' => $config])->save();

        $this->get('/app/cro/experiences/'.$experience->id.'/edit', $this->as($owner))->assertOk()
            ->assertSee('Timer type')->assertSee('Minutes, from when the shopper reaches checkout')->assertSee('Timer length (minutes)')->assertSee('Start again (e.g. every 10 minutes)');

        $options = Registry::type('countdown')['content']['mode']['options'];
        $this->assertSame(['date', 'hours', 'minutes', 'daily'], array_keys($options));
        $storefront = Schema::defaults('countdown');
        $storefront['content']['mode'] = 'hours';
        $this->assertSame([], Schema::normalize('countdown', $storefront)[1], 'Hour timers need no end date.');
    }
}
