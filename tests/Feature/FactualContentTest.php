<?php

namespace Tests\Feature;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/**
 * App Store requirement 1.1.4: only factual information. Merchants can't type reviews, ratings,
 * review counts or "reserved" counts, and timers only count to a real date or daily cutoff.
 * Blocks saved before these options were removed stop showing made-up content.
 */
class FactualContentTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(fn (Request $request) => match (true) {
            str_contains($request['query'] ?? '', 'currentAppInstallation { id } shop {') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => 'gid://shopify/Shop/7']]]),
            default => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
        });
    }

    public function test_the_editor_no_longer_offers_typed_reviews_ratings_goals_or_restarting_timers(): void
    {
        $trust = Registry::type('trust');
        $this->assertSame(['trust-row', 'guarantee-card'], array_keys($trust['templates']));
        $this->assertArrayNotHasKey('reviews', $trust['content']);
        $this->assertArrayNotHasKey('rating', $trust['content']);
        $this->assertArrayNotHasKey('review_count', $trust['content']);

        $this->assertArrayNotHasKey('checkout-reviews', Registry::creatable());
        $this->assertNotContains('checkout-reviews', Registry::feature('checkout')['types']);

        $preorder = Registry::type('preorder');
        $this->assertArrayNotHasKey('goal_current', $preorder['content']);
        $this->assertSame(['time', 'none'], array_keys($preorder['content']['progress']['options']));

        $this->assertSame(['date', 'daily'], array_keys(Registry::type('countdown')['content']['mode']['options']));
        $this->assertArrayNotHasKey('repeat', Registry::type('countdown')['content']);
        $this->assertSame(['date'], array_keys(Registry::type('checkout-countdown')['content']['mode']['options']));
        $this->assertArrayNotHasKey('cart-reservation', Registry::type('checkout-countdown')['templates']);
    }

    public function test_blocks_saved_with_made_up_content_stop_showing_or_lose_it(): void
    {
        $manager = app(ExperienceManager::class);
        $store = $this->installedStore(['plan' => 'scale', 'capabilities' => ['checkout_blocks' => true]]);

        // Blocks as they could be saved before: reviews typed in, a restarting timer, a typed "reserved" count.
        $reviews = $manager->create($store, 'trust', 'trust-row', null);
        $manager->publish($reviews, null);
        $reviews->fresh()->publishedVersion->forceFill(['template_key' => 'review-card'])->save();

        $badges = $manager->create($store, 'trust', 'trust-row', null);
        $manager->publish($badges, null);
        $version = $badges->fresh()->publishedVersion;
        $version->forceFill(['config' => array_replace_recursive($version->config, ['content' => ['rating' => 4.9, 'review_count' => 1200, 'reviews' => [['author' => 'Sara', 'rating' => 5, 'quote' => 'Made up']]]])])->save();

        $timer = $manager->create($store, 'countdown', 'minimal', null);
        $config = $timer->draft_config;
        $config['content']['ends_at'] = now()->addDays(2)->toIso8601String();
        $manager->saveDraft($timer, Schema::normalize('countdown', $config)[0], [], null);
        $manager->publish($timer->fresh(), null);
        $version = $timer->fresh()->publishedVersion;
        $version->forceFill(['config' => array_replace_recursive($version->config, ['content' => ['mode' => 'minutes', 'minutes' => 10, 'repeat' => 'restart']])])->save();

        $preorder = $manager->create($store, 'preorder', 'classic-card', null);
        $config = $preorder->draft_config;
        $config['content']['products'] = [['id' => 'gid://shopify/Product/1', 'title' => 'Jacket']];
        $config['content']['ship_date'] = now()->addMonth()->toDateString();
        $manager->saveDraft($preorder, Schema::normalize('preorder', $config)[0], [], null);
        $manager->publish($preorder->fresh(), null);
        $version = $preorder->fresh()->publishedVersion;
        $version->forceFill(['config' => array_replace_recursive($version->config, ['content' => ['progress' => 'goal', 'goal_target' => 100, 'goal_current' => 64]])])->save();

        $live = collect(app(StorefrontPublisher::class)->payload($store)['experiences']);

        $this->assertNull($live->firstWhere('id', $reviews->handle), 'A review layout no longer shows.');
        $this->assertNull($live->firstWhere('id', $timer->handle), 'A timer that starts per visitor no longer shows.');

        $kept = $live->firstWhere('id', $badges->handle);
        $this->assertNotNull($kept, 'A trust row still shows.');
        $this->assertArrayNotHasKey('reviews', $kept['content']);
        $this->assertArrayNotHasKey('rating', $kept['content']);
        $this->assertArrayNotHasKey('review_count', $kept['content']);

        $pre = $live->firstWhere('id', $preorder->handle);
        $this->assertSame('time', $pre['content']['progress']);
        $this->assertArrayNotHasKey('goal_current', $pre['content']);
    }
}
