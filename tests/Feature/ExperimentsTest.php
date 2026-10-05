<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\Experiments\Experiment;
use App\Models\Store;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use App\Services\Experiments\ExperimentManager;
use App\Services\Experiments\Results;
use App\Services\Experiments\Stats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class ExperimentsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private Store $store;

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
        $this->store = $this->installedStore(['plan' => 'growth', 'pixel_token' => str_repeat('a', 40), 'web_pixel_id' => 'gid://shopify/WebPixel/1']);
    }

    private function published(string $type = 'countdown', string $template = 'minimal'): Experience
    {
        $manager = app(ExperienceManager::class);
        $experience = $manager->create($this->store, $type, $template, null, 'Sale countdown');
        if ($type === 'countdown') {
            $config = $experience->draft_config;
            $config['content']['mode'] = 'daily';
            $config['content']['daily_time'] = '23:00';
            $manager->saveDraft($experience, $config, [], null);
        }
        $manager->publish($experience->fresh(), null);

        return $experience->fresh('publishedVersion');
    }

    private function setupInput(Experiment $experiment, array $over = []): array
    {
        return array_replace_recursive([
            'name' => 'Countdown headline test',
            'hypothesis' => 'Urgent copy lifts conversion.',
            'primary_metric' => 'conversion_rate',
            'secondary_metrics' => ['add_to_cart', 'aov'],
            'guardrails' => ['cart_abandonment' => ['enabled' => '1', 'metric' => 'cart_abandonment', 'threshold' => '5']],
            'min_days' => 7, 'min_visitors' => 1000, 'min_conversions' => 100,
            'audience' => ['device' => 'all', 'countries' => 'US, CA'],
            'variants' => [
                'A' => ['name' => 'Control', 'allocation' => 50],
                'B' => ['name' => 'Urgent', 'allocation' => 50, 'template_key' => 'minimal', 'content' => ['headline' => 'Last chance: sale ends in']],
            ],
        ], $over);
    }

    public function test_setup_validation_launch_and_storefront_payload(): void
    {
        $experience = $this->published();
        $manager = app(ExperimentManager::class);
        $experiment = $manager->create($this->store, $experience, null);
        $this->assertSame(['A', 'B'], $experiment->variants->pluck('key')->all());

        // Allocation must total 100; B must change something.
        $errors = $manager->save($experiment, $this->setupInput($experiment, ['variants' => ['A' => ['allocation' => 60], 'B' => ['allocation' => 30]]]));
        $this->assertSame('Traffic must add up to 100% (now 90%).', $errors['variants.allocation']);
        $same = $this->setupInput($experiment);
        $same['variants']['B']['content']['headline'] = $experience->publishedVersion->config['content']['headline'];
        $this->assertSame([], $manager->save($experiment->fresh(), $same));
        $this->assertNull($experiment->fresh()->variants->firstWhere('key', 'B')->content, 'Only changes from the published experience are kept.');
        $this->assertContains('Change at least one variant: a different template, text or design, or hide it.', $manager->launchProblems($experiment->fresh()));

        $this->assertSame([], $manager->save($experiment->fresh(), $this->setupInput($experiment)));
        $experiment = $experiment->fresh(['variants', 'experience']);
        $this->assertSame(['countries' => 'US, CA'], $experiment->audience);
        $this->assertSame(['headline' => 'Last chance: sale ends in'], $experiment->variants->firstWhere('key', 'B')->content);
        $manager->launch($experiment, null);
        $this->assertSame('running', $experiment->fresh()->status);

        $payload = collect(app(StorefrontPublisher::class)->payload($this->store)['experiences'])->firstWhere('id', $experience->handle);
        $this->assertSame($experiment->handle, $payload['x']['id']);
        $this->assertEquals((object) ['countries' => 'US, CA'], $payload['x']['audience']);
        $this->assertSame([['key' => 'A', 'alloc' => 50], ['key' => 'B', 'alloc' => 50, 'template' => 'minimal', 'style' => 'minimal', 'content' => ['headline' => 'Last chance: sale ends in']]], $payload['x']['variants']);

        // One running test per experience; pausing removes the split from the storefront.
        $second = $manager->create($this->store, $experience, null);
        $manager->save($second, $this->setupInput($second));
        $this->assertContains('This widget is already in a running test. Stop that one first.', $manager->launchProblems($second->fresh()));
        $manager->pause($experiment->fresh(), null);
        $this->assertArrayNotHasKey('x', collect(app(StorefrontPublisher::class)->payload($this->store)['experiences'])->firstWhere('id', $experience->handle));
    }

    public function test_unsupported_experiences_holdouts_and_plan(): void
    {
        $this->assertNotNull(ExperimentManager::unsupported(app(ExperienceManager::class)->create($this->store, 'bundles', 'quantity-breaks', null)));
        $this->assertNotNull(ExperimentManager::unsupported(app(ExperienceManager::class)->create($this->store, 'account-support', 'support-card', null)));
        $this->assertNotNull(ExperimentManager::unsupported(app(ExperienceManager::class)->create($this->store, 'post-purchase', array_key_first(\App\Experiences\Registry::type('post-purchase')['templates']), null)));
        $countdown = $this->published();
        $this->assertTrue(ExperimentManager::canHoldout($countdown));
        $this->assertFalse(ExperimentManager::canHoldout(app(ExperienceManager::class)->create($this->store, 'free-gifts', \App\Experiences\Registry::type('free-gifts')['templates'] ? array_key_first(\App\Experiences\Registry::type('free-gifts')['templates']) : 'x', null)), 'Discount types can\'t hide the widget while the discount applies.');

        $this->store->forceFill(['plan' => 'starter'])->save();
        $experiment = app(ExperimentManager::class)->create($this->store, $countdown, null);
        app(ExperimentManager::class)->save($experiment, $this->setupInput($experiment, ['variants' => ['B' => ['hidden' => '1']]]));
        $this->assertTrue($experiment->fresh()->variants->firstWhere('key', 'B')->hidden);
        $this->assertContains('A/B testing isn\'t included in your plan. Upgrade to launch.', app(ExperimentManager::class)->launchProblems($experiment->fresh()));

        // Growth runs A/B tests, but audience targeting is Advanced experimentation (Scale).
        $this->store->forceFill(['plan' => 'growth'])->save();
        $experiment->forceFill(['audience' => ['segments' => [1]]])->save();
        $this->assertContains('Testing a chosen audience is Advanced experimentation, which isn\'t in your plan. Remove the segments or upgrade.', app(ExperimentManager::class)->launchProblems($experiment->fresh()));
        $this->assertNotContains('A/B testing isn\'t included in your plan. Upgrade to launch.', app(ExperimentManager::class)->launchProblems($experiment->fresh()));
    }

    /** Exposures and orders for one variant: $visitors exposed, $buyers of them ordering $value each. */
    private function traffic(Experiment $experiment, string $variant, int $visitors, int $buyers, float $value, string $device = 'desktop'): void
    {
        $rows = [];
        $start = $experiment->started_at->copy()->addHour();
        for ($i = 0; $i < $visitors; $i++) {
            $vid = $variant.'-'.$i;
            $rows[] = ['store_id' => $this->store->id, 'event' => 'expose', 'name' => 'orderorbit:experiment_exposed', 'visitor_id' => $vid, 'experiment_handle' => $experiment->handle, 'variant' => $variant,
                'experience_handle' => $experiment->experience->handle, 'device' => $device, 'occurred_at' => $start, 'quantity' => 1, 'value' => 0];
            if ($i < $buyers) {
                $rows[] = ['store_id' => $this->store->id, 'event' => 'order', 'name' => 'checkout_completed', 'visitor_id' => $vid, 'experiment_handle' => null, 'variant' => null,
                    'experience_handle' => null, 'device' => $device, 'occurred_at' => $start->copy()->addMinutes(10), 'quantity' => 2, 'value' => $value, 'order_ref' => 'o-'.$vid];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            AnalyticsEvent::insert(array_map(fn ($r) => $r + ['order_ref' => null], $chunk));
        }
    }

    private function running(int $daysAgo): Experiment
    {
        $experience = $this->published();
        $manager = app(ExperimentManager::class);
        $experiment = $manager->create($this->store, $experience, null);
        $manager->save($experiment, $this->setupInput($experiment));
        $manager->launch($experiment->fresh(['variants', 'experience']), null);
        $experiment = $experiment->fresh(['variants', 'experience']);
        $experiment->forceFill(['started_at' => now()->subDays($daysAgo)])->save();

        return $experiment;
    }

    public function test_results_wait_for_sample_then_name_a_winner(): void
    {
        $experiment = $this->running(3);
        $this->traffic($experiment, 'A', 1200, 120, 50);
        $this->traffic($experiment, 'B', 1200, 170, 50, 'mobile');
        // An order before exposure doesn't count.
        AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => 'order', 'name' => 'checkout_completed', 'visitor_id' => 'A-500', 'value' => 999, 'order_ref' => 'early', 'occurred_at' => $experiment->started_at->copy()->subHour()]);

        $r = app(Results::class)->for($experiment);
        $this->assertSame([1200, 120, 6000.0], [$r['variants']['A']['visitors'], $r['variants']['A']['conversions'], $r['variants']['A']['revenue']]);
        $this->assertEqualsWithDelta(14.1667, $r['variants']['B']['conversion_rate'], 0.001);
        $this->assertEquals(2, $r['variants']['B']['units_per_order']);
        $this->assertSame(['mobile' => ['visitors' => 1200, 'conversions' => 170]], $r['variants']['B']['devices']);
        $this->assertLessThan(0.05, $r['comparisons']['B']['conversion_rate']['p']);
        $this->assertEqualsWithDelta(41.67, $r['comparisons']['B']['conversion_rate']['lift'], 0.01);
        $this->assertSame('collecting', $r['decision']['state'], 'Significant, but only 3 days in: no winner yet.');
        $this->assertStringContainsString('at least 7 days', $r['decision']['headline']);

        $experiment->forceFill(['started_at' => now()->subDays(8)])->save();
        AnalyticsEvent::where('order_ref', 'early')->delete();
        AnalyticsEvent::query()->update(['occurred_at' => now()->subDays(7)]);
        $r = app(Results::class)->for($experiment->fresh(['variants', 'experience']));
        $this->assertSame(['winner', 'B'], [$r['decision']['state'], $r['decision']['winner']]);
        $this->assertStringContainsString('Variant B wins: +41.7% conversion rate vs control', $r['decision']['headline']);
        $this->assertFalse($r['comparisons']['B']['guardrails']['cart_abandonment']['breached']);
    }

    public function test_no_clear_winner_and_bonferroni(): void
    {
        $experiment = $this->running(10);
        $this->traffic($experiment, 'A', 1500, 150, 40);
        $this->traffic($experiment, 'B', 1500, 160, 40);
        $r = app(Results::class)->for($experiment);
        $this->assertSame('no_winner', $r['decision']['state']);
        $this->assertEqualsWithDelta(0.05, $r['alpha'], 1e-9);
        $this->assertEqualsWithDelta(0.025, Stats::alpha(3), 1e-9, 'A/B/C halves the significance level per comparison.');
    }

    public function test_screens_lifecycle_and_apply_winner(): void
    {
        $owner = $this->member($this->store, 'owner');
        $experience = $this->published();

        $this->page('/app/experiments', $owner)->assertOk()->assertJsonPath('component', 'experiments/index')->assertSee('Sale countdown');
        $this->post('/app/experiments', ['experience_id' => $experience->id], $this->as($owner))->assertRedirectContains('/edit');
        $experiment = Experiment::sole();
        $this->page('/app/experiments/'.$experiment->id.'/edit', $owner)->assertOk()->assertJsonPath('component', 'experiments/edit')
            ->assertJsonPath('props.canHoldout', true)->assertJsonPath('props.previews.A.type', 'countdown')->assertJsonPath('props.experiment.variants.B.key', 'B');

        $input = $this->setupInput($experiment) + ['action' => 'launch'];
        $this->post('/app/experiments/'.$experiment->id, $input, $this->as($owner))->assertRedirectContains('notice=experiment_launched');
        $this->page('/app/experiments/'.$experiment->id, $owner)->assertOk()->assertJsonPath('props.results.decision.state', 'collecting')->assertSee('Launched with A 50%, B 50%.');
        $this->get('/app/experiments/'.$experiment->id.'/export', $this->as($owner))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->post('/app/experiments/'.$experiment->id.'/pause', [], $this->as($owner))->assertRedirectContains('experiment_pause');
        $this->post('/app/experiments/'.$experiment->id.'/resume', [], $this->as($owner))->assertRedirectContains('experiment_resume');
        $this->assertSame('running', $experiment->fresh()->status);

        // Applying B publishes its text to the experience and completes the test.
        $this->post('/app/experiments/'.$experiment->id.'/apply', ['variant' => 'B'], $this->as($owner))->assertRedirectContains('experiment_apply');
        $experiment->refresh();
        $this->assertSame('completed', $experiment->status);
        $this->assertSame('Last chance: sale ends in', $experience->fresh('publishedVersion')->publishedVersion->config['content']['headline']);
        $this->page('/app/experiments?tab=completed', $owner)->assertOk()->assertJsonPath('props.experiments.0.name', 'Countdown headline test');

        // Pixel events carry the test and variant.
        $this->call('POST', '/api/pixel', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['t' => str_repeat('a', 40), 's' => $this->store->shop_domain, 'k' => 'e', 'e' => 'experiment_exposed', 'x' => $experience->handle, 'ty' => 'countdown', 'xp' => $experiment->handle, 'xv' => 'B', 'vid' => 'v1']));
        $this->assertSame(['expose', 'orderorbit:experiment_exposed', $experiment->handle, 'B'], array_values(AnalyticsEvent::where('event', 'expose')->sole()->only(['event', 'name', 'experiment_handle', 'variant'])));
    }

    public function test_checkout_and_thank_you_blocks_can_be_tested(): void
    {
        $this->store->forceFill(['capabilities' => ['checkout_blocks' => true]])->save();
        $manager = app(ExperienceManager::class);
        $trust = $manager->create($this->store, 'checkout-trust', 'trust-row', null);
        $manager->publish($trust, null);
        $survey = $manager->create($this->store, 'ty-survey', 'choice-list', null);
        $manager->publish($survey, null);
        $this->assertNull(ExperimentManager::unsupported($trust->fresh()));

        // Thank You blocks are judged on clicks: the order is already placed.
        $experiments = app(ExperimentManager::class);
        $ty = $experiments->create($this->store, $survey->fresh('publishedVersion'), null);
        $this->assertSame('click_rate', $ty->primary_metric);

        $test = $experiments->create($this->store, $trust->fresh('publishedVersion'), null);
        $this->assertSame('conversion_rate', $test->primary_metric);
        $this->assertSame(['countries', 'cart_min', 'cart_max'], ExperimentManager::audienceFor($trust));
        $errors = $experiments->save($test, [
            'name' => 'Trust layout', 'primary_metric' => 'conversion_rate',
            'audience' => ['device' => 'mobile', 'countries' => 'US', 'cart_min' => '25'],
            'variants' => ['A' => ['allocation' => 50], 'B' => ['allocation' => 50, 'template_key' => 'icon-grid', 'design' => ['ck_background' => 'subdued']]],
        ]);
        $this->assertSame([], $errors);
        $test = $test->fresh(['variants', 'experience', 'store']);
        $this->assertEquals(['countries' => 'US', 'cart_min' => 25], $test->audience, 'Checkout doesn\'t know the device.');
        $experiments->launch($test, null);

        $block = collect(app(StorefrontPublisher::class)->checkoutPayload($this->store)['experiences'])->firstWhere('type', 'checkout-trust');
        $this->assertSame($test->handle, $block['x']['id']);
        $this->assertSame(['key' => 'B', 'alloc' => 50, 'template' => 'icon-grid', 'style' => 'grid', 'design' => ['ck_background' => 'subdued']], $block['x']['variants'][1]);

        // Clicks per visitor decide click-through tests.
        $test->forceFill(['started_at' => now()->subHour()])->save();
        $start = now()->subMinutes(30);
        foreach (['A' => 10, 'B' => 30] as $variant => $clicks) {
            for ($i = 0; $i < 40; $i++) {
                AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => 'expose', 'name' => 'orderorbit:experiment_exposed', 'visitor_id' => $variant.$i, 'experiment_handle' => $test->handle, 'variant' => $variant, 'occurred_at' => $start]);
                if ($i < $clicks) {
                    AnalyticsEvent::create(['store_id' => $this->store->id, 'event' => 'click', 'name' => 'orderorbit:checkout_block_clicked', 'visitor_id' => $variant.$i, 'experience_handle' => $trust->handle, 'occurred_at' => $start->copy()->addMinute()]);
                }
            }
        }
        $test->forceFill(['primary_metric' => 'click_rate'])->save();
        $r = app(Results::class)->for($test->fresh(['variants', 'experience']));
        $this->assertSame([25.0, 75.0], [$r['variants']['A']['click_rate'], $r['variants']['B']['click_rate']]);
        $this->assertLessThan(0.001, $r['comparisons']['B']['click_rate']['p']);
        $this->assertStringContainsString('clicks per variant', $r['decision']['headline']);
    }

    public function test_the_guide_is_public_and_linked_from_the_app(): void
    {
        config(['site.preview_password' => 'secret']);
        $this->get('/docs/ab-testing')->assertOk()->assertSee('Run A/B tests')->assertSee('Set up a test, step by step')->assertSee('The statistical method')->assertSee('21,200');
        $this->get('/pricing')->assertSee('Coming soon', false); // the rest of the site stays behind the preview gate
        $this->get('/docs/unknown')->assertNotFound();

        $owner = $this->member($this->store, 'owner');
        $this->page('/app/experiments', $owner)->assertOk()->assertJsonPath('props.docsUrl', url('/docs/ab-testing'));
    }
}
