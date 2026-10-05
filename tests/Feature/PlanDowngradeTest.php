<?php

namespace Tests\Feature;

use App\Automation\Engine;
use App\Automation\WorkflowManager;
use App\Models\Experience;
use App\Services\Billing\Entitlements;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/**
 * Moving a store to a lower plan takes away what that plan doesn't include everywhere it runs:
 * storefront, checkout and Thank You, customer accounts, Shopify discounts and workflows.
 * Nothing is deleted, and moving back up brings it all back.
 */
class PlanDowngradeTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(function (Request $request) {
            $query = $request['query'] ?? '';

            return match (true) {
                str_contains($query, 'discountAutomaticAppCreate') => Http::response(['data' => ['discountAutomaticAppCreate' => ['automaticAppDiscount' => ['discountId' => 'gid://shopify/DiscountAutomaticNode/78'], 'userErrors' => []]]]),
                str_contains($query, 'discountAutomaticDelete') => Http::response(['data' => ['discountAutomaticDelete' => ['userErrors' => []]]]),
                str_contains($query, 'currentAppInstallation { id }') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => 'gid://shopify/Shop/1', 'primaryDomain' => ['url' => 'https://demo.test']]]]),
                str_contains($query, 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
                default => Http::response(['data' => []]),
            };
        });
        app(TemplateLibrary::class)->sync();
    }

    public function test_scale_to_free_removes_scale_features_everywhere_and_back_again(): void
    {
        $store = $this->installedStore(['plan' => 'scale', 'capabilities' => ['new_customer_accounts' => true, 'checkout_blocks' => true]]);
        $manager = app(ExperienceManager::class);
        $live = function (string $type, string $template, array $content = []) use ($manager, $store) {
            $experience = $manager->create($store->fresh(), $type, $template, null);
            $config = $experience->draft_config;
            $config['content'] = array_merge($config['content'], $content);
            $manager->saveDraft($experience, \App\Experiences\Schema::normalize($type, $config)[0], [], null);
            $manager->publish($experience->fresh(), null);

            return $experience->fresh();
        };
        $trust = $live('trust', 'trust-row');                        // every plan
        $survey = $live('ty-survey', 'choice-list');                 // Thank You: Growth and up
        $block = $live('checkout-image', 'wide-banner', ['image' => 'https://cdn.example.com/banner.png']);             // Checkout: Growth and up
        $reorder = $live('account-reorder', 'reorder-card');         // Customer accounts: Scale
        // A quantity-break bundle with a Shopify discount behind it (Starter and up).
        $bundle = $manager->create($store->fresh(), 'bundles', 'qb-classic', null);
        $bundle->forceFill(['status' => 'published', 'published_at' => now()->subDay(), 'published_version_id' => $bundle->versions()->create(['version' => 1, 'config' => $bundle->draft_config, 'template_key' => 'qb-classic'])->id, 'shopify_discount_id' => 'gid://shopify/DiscountAutomaticNode/77'])->save();

        $publisher = app(StorefrontPublisher::class);
        $types = fn (array $payload) => collect($payload['experiences'])->pluck('type')->sort()->values()->all();
        $this->assertSame(['bundles', 'trust'], $types($publisher->payload($store->fresh())));
        $this->assertSame(['checkout-image', 'ty-survey'], $types($publisher->checkoutPayload($store->fresh())));
        $this->assertSame(['account-reorder'], $types($publisher->accountPayload($store->fresh())));

        // Two workflows, one with branching and a webhook.
        $workflows = app(WorkflowManager::class);
        $simple = $workflows->create($store->fresh(), null, null, 'Simple');
        $advanced = $workflows->create($store->fresh(), null, null, 'Advanced');
        $simple->forceFill(['status' => 'enabled', 'updated_at' => now()->subHour()])->save();
        $advanced->forceFill(['status' => 'enabled'])->save();
        $program = [['t' => 'cond', 'else' => 2], ['t' => 'action', 'action' => 'webhook']];
        $this->assertNull(app(Engine::class)->blocked($store->fresh(), $program));

        // Scale → Free.
        $store->forceFill(['plan' => 'free'])->save();
        $this->assertTrue(app(Entitlements::class)->apply($store->fresh()));
        $store->refresh();

        $this->assertSame(['trust'], $types($publisher->payload($store)), 'The quantity-break bundle stops: Free has bundles but not quantity breaks.');
        $this->assertSame([], $publisher->checkoutPayload($store)['experiences'], 'Checkout and Thank You blocks stop.');
        $this->assertSame([], $publisher->accountPayload($store)['experiences'], 'Customer account blocks stop.');
        Http::assertSent(fn (Request $r) => str_contains($r['query'] ?? '', 'discountAutomaticDelete'));
        $this->assertNull($bundle->fresh()->shopify_discount_id, 'Its Shopify discount is removed, so checkout no longer applies the bundle price.');
        Http::assertSent(fn (Request $r) => str_contains($r['query'] ?? '', 'metafieldsSet') && str_contains(json_encode($r['variables'] ?? []), '\\"experiences\\":[]'));

        // Limits: Free has 1 workflow and 1 active experience; the plan's own features stay on.
        $this->assertSame(['disabled', 'enabled'], [$simple->fresh()->status, $advanced->fresh()->status], 'The newest workflow stays on.');
        $this->assertStringContainsString('branching', app(Engine::class)->blocked($store, $program));
        $this->assertSame('published', $trust->fresh()->status);
        foreach ([$survey, $block, $reorder] as $experience) {
            $this->assertSame('published', $experience->fresh()->status, 'Not deleted or unpublished: it just stops showing.');
        }

        // Nothing changed since: applying again does nothing.
        $this->assertFalse(app(Entitlements::class)->apply($store->fresh()));

        // Back to Scale: everything that's still published shows again.
        $store->forceFill(['plan' => 'scale'])->save();
        $this->assertTrue(app(Entitlements::class)->apply($store->fresh()));
        $this->assertSame(['checkout-image', 'ty-survey'], $types($publisher->checkoutPayload($store->fresh())));
        $this->assertSame(['account-reorder'], $types($publisher->accountPayload($store->fresh())));
        $this->assertSame('gid://shopify/DiscountAutomaticNode/78', $bundle->fresh()->shopify_discount_id, 'The bundle\'s discount is back.');
    }

    public function test_plan_changes_from_the_admin_and_expired_comp_plans_are_applied_on_schedule(): void
    {
        $store = $this->installedStore(['plan' => 'free', 'capabilities' => ['new_customer_accounts' => true], 'access_token_expires_at' => now()->addMonth()]);
        $store->forceFill(['entitlements' => ['plan' => 'scale', 'plan_until' => now()->addDay()->toDateString()]])->save();
        $manager = app(ExperienceManager::class);
        $manager->publish($manager->create($store, 'ty-survey', 'choice-list', null), null);
        $this->artisan('orderorbit:apply-entitlements')->assertSuccessful();
        $this->assertNotNull($store->fresh()->entitlements_hash);
        $this->assertCount(1, app(StorefrontPublisher::class)->checkoutPayload($store->fresh())['experiences']);

        // The complimentary Scale plan ends: the scheduler notices and the block stops.
        $this->travel(3)->days();
        $this->assertSame('free', $store->fresh()->effectivePlan());
        $this->artisan('orderorbit:apply-entitlements')->expectsOutputToContain('Applied to 1 store')->assertSuccessful();
        $this->assertSame([], app(StorefrontPublisher::class)->checkoutPayload($store->fresh())['experiences']);
        $this->assertSame(Experience::where('type', 'ty-survey')->value('status'), 'published');
    }
}
