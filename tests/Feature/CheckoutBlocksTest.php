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
            str_contains($request['query'] ?? '', 'currentAppInstallation { id } shop {') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => 'gid://shopify/Shop/7']]]),
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

    public function test_fifteen_block_types_with_checkout_only_settings(): void
    {
        $checkout = array_keys(array_filter(Registry::types(), fn ($t) => $t['surface'] === 'checkout'));
        $thankYou = array_keys(array_filter(Registry::types(), fn ($t) => $t['surface'] === 'thank-you'));
        $this->assertCount(7, $checkout);
        $this->assertCount(8, $thankYou);

        // Checkout uses the store's checkout branding: Shopify's box styles only, and only the targeting checkout knows.
        $fields = Schema::fields('checkout-trust');
        $this->assertSame(['ck_background', 'ck_border', 'ck_border_style', 'ck_radius', 'ck_padding', 'ck_width', 'ck_width_value', 'ck_height', 'ck_height_value', 'ck_text', 'ck_tone'], array_keys($fields['design']));
        $this->assertSame([], Schema::fields('post-purchase')['design']);
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
        $answers = collect($this->page('/app/cro/experiences/'.$survey->id, $owner)->assertOk()->json('props.survey'))->pluck('total', 'label');
        $this->assertSame(2, $answers['Instagram']);
        $this->assertSame(67, (int) round($answers['Instagram'] / $answers->sum() * 100));
    }

    public function test_app_screens_explain_where_blocks_go(): void
    {
        $owner = $this->member($plus = $this->installedStore(['plan' => 'growth', 'capabilities' => ['checkout_blocks' => true]]), 'owner');
        $checkout = $this->page('/app/cro/features/checkout', $owner)->assertOk()->assertJsonPath('props.notice', null)->assertJsonPath('props.editor.label', 'Open checkout editor');
        $this->assertContains('checkout reviews block', array_column($checkout->json('props.types'), 'singular'));
        $this->assertStringContainsString('settings/checkout/editor', $checkout->json('props.editor.url'));
        $thankYou = $this->page('/app/cro/features/thank-you', $owner)->assertOk();
        $this->assertContains('survey', array_column($thankYou->json('props.types'), 'singular'));
        $this->assertStringContainsString('page=thank-you', $thankYou->json('props.editor.url'));
        $features = array_column($this->page('/app/cro', $owner)->assertOk()->json('props.features'), 'label');
        $this->assertContains('Checkout blocks', $features);
        $this->assertContains('Thank You & Order Status', $features);

        $this->post('/app/cro/experiences', ['type' => 'checkout-gift', 'template' => 'reward-card'], $this->as($owner))->assertRedirectContains('/edit');
        $this->page('/app/cro/experiences/1/edit', $owner)->assertOk()->assertJsonPath('props.designNote', 'checkout-boxes')
            ->assertJsonPath('props.experience.type', 'checkout-gift')->assertSee('Corner radius')->assertSee('Claim button');

        // Without Shopify Plus the in-checkout blocks aren't offered; without Growth, publishing is explained.
        $plus->forceFill(['plan' => 'starter', 'capabilities' => ['checkout_blocks' => false]])->save();
        $this->page('/app/cro/features/checkout', $owner)->assertOk()->assertJsonPath('props.notice', 'plus');
        $this->page('/app/cro/features/thank-you', $owner)->assertOk()->assertJsonPath('props.notice', 'plan');
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

    public function test_image_blocks_and_box_styles_reach_the_extension(): void
    {
        $manager = app(ExperienceManager::class);
        $store = $this->installedStore(['plan' => 'growth', 'capabilities' => ['checkout_blocks' => true]]);

        // An image is required, and only https images and links are accepted.
        $errors = Schema::normalize('checkout-image', Schema::defaults('checkout-image'))[1];
        $this->assertSame('Add an image: upload one or paste its link.', $errors['content.image']);
        $bad = Schema::defaults('ty-image');
        $bad['content'] = array_merge($bad['content'], ['image' => 'http://example.com/a.png', 'link_url' => 'javascript:alert(1)', 'img_width' => 'percent', 'img_width_value' => 150]);
        $bad['design']['ck_width'] = 'percent';
        $bad['design']['ck_width_value'] = 120;
        $this->assertEqualsCanonicalizing(['content.image', 'content.link_url', 'content.img_width_value', 'design.ck_width_value'], array_keys(Schema::normalize('ty-image', $bad)[1]));

        // The Framed template presets its box; the merchant's styles are published with the block.
        $framed = TemplateLibrary::defaults('ty-image', 'framed');
        $this->assertSame(['subdued', 'large', 'dashed'], [$framed['design']['ck_background'], $framed['design']['ck_border'], $framed['design']['ck_border_style']]);

        $image = $manager->create($store, 'checkout-image', 'wide-banner', null);
        $config = $image->draft_config;
        $config['content']['image'] = 'https://cdn.shopify.com/s/files/1/banner.png';
        $config['content']['link_url'] = '/collections/all';
        $config['design'] = array_merge($config['design'], ['ck_background' => 'subdued', 'ck_radius' => 'large', 'ck_width' => 'px', 'ck_width_value' => 480, 'ck_tone' => 'success']);
        [$clean, $errors] = Schema::normalize('checkout-image', $config);
        $this->assertSame([], $errors);
        $manager->saveDraft($image, $clean, [], null);
        $manager->publish($image->fresh(), null);
        $trust = $this->ready($manager, $store, 'checkout-trust', 'trust-row');
        $manager->publish($trust, null);

        $blocks = collect(app(StorefrontPublisher::class)->checkoutPayload($store)['experiences'])->keyBy('type');
        $this->assertSame(['plain', '21/9', '/collections/all'], [$blocks['checkout-image']['style'], $blocks['checkout-image']['content']['img_ratio'], $blocks['checkout-image']['content']['link_url']]);
        $this->assertSame(['subdued', 'large', 'px', 480, 'success'], array_values(array_intersect_key($blocks['checkout-image']['design'], array_flip(['ck_background', 'ck_radius', 'ck_width', 'ck_width_value', 'ck_tone']))));
        $this->assertSame('auto', $blocks['checkout-trust']['design']['ck_background'], 'Other blocks keep their layout\'s look by default.');
    }

    public function test_images_upload_to_shopify_files(): void
    {
        config(['shopify.scopes' => $granted = 'read_products,write_discounts,write_files']);
        $store = $this->installedStore(['plan' => 'growth', 'scopes' => $granted]);
        $owner = $this->member($store, 'owner');
        Http::swap(new \Illuminate\Http\Client\Factory); // replace the suite's catch-all fake
        Http::fake(fn (Request $request) => match (true) {
            str_contains($request->url(), '/admin/oauth/access_token') => Http::response(['access_token' => 'shpat_new', 'scope' => $granted, 'expires_in' => 3600, 'refresh_token' => 'r', 'refresh_token_expires_in' => 7776000]),
            str_contains($request->url(), 'storage.googleapis.com') => Http::response('', 204),
            str_contains($request['query'] ?? '', 'stagedUploadsCreate') => Http::response(['data' => ['stagedUploadsCreate' => ['stagedTargets' => [['url' => 'https://storage.googleapis.com/upload', 'resourceUrl' => 'https://storage.googleapis.com/upload/banner.png', 'parameters' => [['name' => 'key', 'value' => 'abc']]]], 'userErrors' => []]]]),
            str_contains($request['query'] ?? '', 'fileCreate') => Http::response(['data' => ['fileCreate' => ['files' => [['id' => 'gid://shopify/MediaImage/9', 'fileStatus' => 'UPLOADED']], 'userErrors' => []]]]),
            str_contains($request['query'] ?? '', 'node(id') => Http::response(['data' => ['node' => ['id' => 'gid://shopify/MediaImage/9', 'fileStatus' => 'READY', 'image' => ['url' => 'https://cdn.shopify.com/s/files/1/banner.png']]]]),
            default => Http::response(['data' => []]),
        });

        $file = \Illuminate\Http\UploadedFile::fake()->image('Summer banner.png', 1200, 400);
        $this->post('/app/uploads/image', ['image' => $file], $this->as($owner) + ['Accept' => 'application/json'])
            ->assertJson(['url' => 'https://cdn.shopify.com/s/files/1/banner.png'])->assertOk();
        Http::assertSent(fn (Request $r) => str_contains($r['query'] ?? '', 'stagedUploadsCreate') && json_decode($r->body(), true)['variables']['input'][0]['filename'] === 'orderorbit-Summer-banner.png');

        $this->post('/app/uploads/image', ['image' => \Illuminate\Http\UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], $this->as($owner) + ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJson(['message' => 'Use a JPG, PNG, GIF or WebP image.']);

        // Stores that haven't approved the new permission are told how to fix it.
        $store->forceFill(['scopes' => 'read_products,write_discounts'])->save();
        $response = app(\App\Http\Controllers\App\UploadController::class)->image(\Illuminate\Http\Request::create('/', 'POST', [], [], ['image' => $file]), $store, app(\App\Services\Shopify\Files::class));
        $this->assertSame(403, $response->status());
        $this->assertStringContainsString('approve', $response->getData(true)['message']);
    }
}
