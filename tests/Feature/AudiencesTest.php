<?php

namespace Tests\Feature;

use App\Automation\Context;
use App\Automation\Engine;
use App\Automation\WorkflowManager;
use App\Models\Audiences\PersonalizationRule;
use App\Models\Audiences\Segment;
use App\Models\Automation\InboxItem;
use App\Models\Store;
use App\Services\Audiences\Audiences;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class AudiencesTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private Store $store;

    private ?string $countQuery = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(function (Request $request) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'customersCount')) {
                $this->countQuery = json_decode($request->body(), true)['variables']['q'];

                return Http::response(['data' => ['customersCount' => ['count' => 42]]]);
            }

            return match (true) {
                str_contains($query, 'currentAppInstallation { id } shop {') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => 'gid://shopify/Shop/7']]]),
                str_contains($query, 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
                default => Http::response(['data' => []]),
            };
        });
        $this->store = $this->installedStore(['plan' => 'scale']);
    }

    public function test_segment_rules_validation_counts_and_server_matching(): void
    {
        [$match, $rules, $errors] = Audiences::normalize(['match' => 'any', 'rules' => [
            ['field' => 'orders_count', 'op' => 'gte', 'value' => '3'],
            ['field' => 'customer_tag', 'op' => 'has', 'value' => 'VIP'],
            ['field' => 'country', 'op' => 'in', 'value' => 'us, ca'],
            ['field' => 'country', 'op' => 'in', 'value' => 'USA'],
            ['field' => 'product_purchased', 'op' => 'any', 'value' => json_encode([['id' => 'gid://shopify/Product/7', 'title' => 'Serum']])],
            ['field' => 'nope', 'op' => 'x', 'value' => 1],
        ]]);
        $this->assertSame('any', $match);
        $this->assertSame(['field' => 'orders_count', 'op' => 'gte', 'value' => 3], $rules[0]);
        $this->assertSame('US, CA', $rules[2]['value']);
        $this->assertSame([['id' => '7', 'title' => 'Serum']], $rules[4]['value']);
        $this->assertSame(['rules.3'], array_keys($errors));
        $this->assertArrayHasKey('rules', Audiences::normalize(['rules' => []])[2]);

        // Shopify counts segments made only of customer fields.
        $vip = Segment::create(['store_id' => $this->store->id, 'name' => 'VIP', 'match' => 'any', 'rules' => [['field' => 'orders_count', 'op' => 'gte', 'value' => 3], ['field' => 'ltv', 'op' => 'gte', 'value' => 500]]]);
        $this->assertSame(42, app(Audiences::class)->count($this->store, $vip));
        $this->assertSame('orders_count:>=3 OR total_spent:>=500', $this->countQuery);
        $mobile = Segment::create(['store_id' => $this->store->id, 'name' => 'Mobile', 'match' => 'all', 'rules' => [['field' => 'device', 'op' => 'is', 'value' => 'mobile']]]);
        $this->assertFalse(Audiences::countable($mobile));
        $this->assertNull(app(Audiences::class)->count($this->store, $mobile));

        // Workflows check segments against the run's customer and order.
        $context = ['customer' => ['id' => 9, 'orders_count' => 4, 'total_spent' => 210, 'tags' => ['vip']], 'order' => ['products' => ['7'], 'country' => 'US', 'created_at' => now()->toIso8601String()]];
        $this->assertTrue(Audiences::matchesContext($vip, $context));
        $this->assertFalse(Audiences::matchesContext($mobile, $context), 'Device is unknown on the server.');
        $buyers = Segment::create(['store_id' => $this->store->id, 'name' => 'Serum buyers', 'match' => 'all', 'rules' => [['field' => 'product_purchased', 'op' => 'any', 'value' => [['id' => '7', 'title' => 'Serum']]], ['field' => 'customer_tag', 'op' => 'has', 'value' => 'VIP']]]);
        $this->assertTrue(Audiences::matchesContext($buyers, $context));
    }

    private function countdown(): \App\Models\Experience
    {
        $manager = app(ExperienceManager::class);
        $experience = $manager->create($this->store, 'countdown', 'minimal', null, 'Sale countdown');
        $config = $experience->draft_config;
        $config['content']['mode'] = 'daily';
        $config['content']['daily_time'] = '23:00';
        $manager->saveDraft($experience, $config, [], null);
        $manager->publish($experience->fresh(), null);

        return $experience->fresh();
    }

    public function test_rules_and_segment_targeting_reach_the_storefront(): void
    {
        $owner = $this->member($this->store, 'owner');
        $experience = $this->countdown();
        $returning = Segment::create(['store_id' => $this->store->id, 'name' => 'Returning', 'match' => 'all', 'rules' => [['field' => 'orders_count', 'op' => 'gte', 'value' => 1]]]);
        $mobile = Segment::create(['store_id' => $this->store->id, 'name' => 'Mobile', 'match' => 'all', 'rules' => [['field' => 'device', 'op' => 'is', 'value' => 'mobile']]]);

        // A rule needs a segment or a condition; swap needs a template.
        $this->send('/app/audiences/rules', ['experience_id' => $experience->id, 'outcome' => 'show'], $owner)->assertStatus(422)->assertJsonPath('props.fieldErrors.segments', 'Choose at least one segment or condition: who is this rule for?');
        $this->send('/app/audiences/rules', ['experience_id' => $experience->id, 'outcome' => 'swap', 'segments' => [$mobile->id]], $owner)->assertStatus(422)->assertJsonPath('props.fieldErrors.template_key', 'Choose the template to show.');

        $this->post('/app/audiences/rules', ['experience_id' => $experience->id, 'outcome' => 'swap', 'template_key' => 'banner', 'segments' => [$mobile->id], 'name' => 'Mobile banner'], $this->as($owner))->assertRedirectContains('notice=saved');
        $this->post('/app/audiences/rules', ['experience_id' => $experience->id, 'outcome' => 'show', 'segments' => [$returning->id], 'conditions' => ['cart_min' => '75', 'device' => 'tablet']], $this->as($owner))->assertRedirectContains('notice=saved');
        $this->assertEquals(['cart_min' => 75], PersonalizationRule::latest('id')->first()->conditions, 'Only known devices are kept.');

        $p = app(StorefrontPublisher::class)->payload($this->store)['p'];
        $this->assertSame([(string) $returning->id, (string) $mobile->id], array_map('strval', array_keys((array) $p['segments'])));
        $rules = json_decode(json_encode($p['rules']), true);
        $this->assertSame(['experience' => $experience->handle, 'segments' => [(string) $mobile->id], 'outcome' => 'swap', 'template' => 'banner', 'style' => 'banner'], $rules[0]);
        $this->assertSame(['experience' => $experience->handle, 'segments' => [(string) $returning->id], 'when' => ['cart_min' => 75], 'outcome' => 'show'], $rules[1]);

        $this->page('/app/audiences/rules', $owner)->assertOk()->assertJsonCount(1, 'props.conflicts')->assertJsonPath('props.rules.0.name', 'Mobile banner');
        // Reorder: the second rule moves up.
        $second = PersonalizationRule::latest('id')->first();
        $this->post('/app/audiences/rules/'.$second->id.'/up', [], $this->as($owner))->assertRedirect();
        $this->assertSame($experience->handle, app(StorefrontPublisher::class)->payload($this->store)['p']['rules'][0]['experience']);
        $this->assertSame('show', app(StorefrontPublisher::class)->payload($this->store)['p']['rules'][0]['outcome']);

        // Experience targeting by segment; segments in use can't be archived.
        $config = $experience->fresh()->draft_config;
        $config['targeting']['segments'] = [(string) $returning->id, 'x'];
        [$clean] = \App\Experiences\Schema::normalize('countdown', $config);
        $this->assertSame([$returning->id], $clean['targeting']['segments']);
        $this->post('/app/audiences/segments/'.$returning->id.'/archive', [], $this->as($owner))->assertRedirectContains('error=');
        $this->assertNull($returning->fresh()->archived_at);

        // Growth has basic personalization only: segments on order history (Advanced) are left out.
        $this->store->forceFill(['plan' => 'growth'])->save();
        $this->assertArrayNotHasKey((string) $returning->id, (array) (app(StorefrontPublisher::class)->payload($this->store)['p']['segments'] ?? []));
        // Without personalization in the plan, the storefront gets nothing to apply.
        $this->store->forceFill(['plan' => 'starter'])->save();
        $this->assertArrayNotHasKey('p', app(StorefrontPublisher::class)->payload($this->store));
    }

    public function test_screens_and_segment_builder(): void
    {
        $owner = $this->member($this->store, 'owner');
        $names = array_column($this->page('/app/audiences/segments', $owner)->assertOk()->json('props.templates'), 'name');
        $this->assertContains('VIP customers', $names);
        $this->assertContains('Lapsed customers', $names);
        $this->post('/app/audiences/segments', ['template' => 'vip'], $this->as($owner))->assertRedirectContains('/app/audiences/segments/');
        $segment = Segment::sole();
        $this->assertSame([42, 'VIP customers'], [$segment->member_count, $segment->name]);

        $this->page('/app/audiences/segments/'.$segment->id, $owner)->assertOk()->assertJsonPath('props.segment.member_count', 42)->assertJsonPath('props.usage', []);
        $this->post('/app/audiences/segments/'.$segment->id, ['name' => 'Big spenders', 'match' => 'all', 'rules' => [['field' => 'ltv', 'op' => 'gte', 'value' => '1000'], ['field' => 'device', 'op' => 'is', 'value' => 'mobile']]], $this->as($owner))->assertRedirectContains('notice=saved');
        $segment->refresh();
        $this->assertSame(['Big spenders', null], [$segment->name, $segment->member_count], 'Browsing rules can\'t be counted.');

        $this->post('/app/audiences/segments/'.$segment->id.'/duplicate', [], $this->as($owner))->assertRedirect();
        $this->assertSame(2, Segment::count());
        $this->post('/app/audiences/segments/'.$segment->id.'/archive', [], $this->as($owner))->assertRedirectContains('segment_archived');
        $this->page('/app/audiences/segments?archived=1', $owner)->assertOk()->assertJsonPath('props.segments.0.name', 'Big spenders');

        $this->page('/app/audiences/rules/new', $owner)->assertOk()->assertJsonPath('component', 'audiences/rule')->assertJsonPath('props.outcomes.swap', 'Show it in another template');
    }

    public function test_workflows_can_check_a_segment(): void
    {
        $vip = Segment::create(['store_id' => $this->store->id, 'name' => 'VIP', 'match' => 'all', 'rules' => [['field' => 'orders_count', 'op' => 'gte', 'value' => 3]]]);
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create($this->store, null, null, 'VIP thanks');
        $this->assertSame([], $manager->saveDraft($workflow, ['trigger' => 'order_paid', 'steps' => [
            ['type' => 'condition', 'match' => 'all', 'rules' => [['field' => 'segment', 'op' => 'is', 'value' => (string) $vip->id]],
                'then' => [['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'VIP order']]], 'else' => []],
        ]], 'VIP thanks'));
        $manager->publish($workflow->fresh(), null);
        $this->assertCount(1, Audiences::usage($this->store, $vip->id)['workflows']);

        $order = fn ($id, $count) => Context::fromOrder(['id' => $id, 'name' => '#'.$id, 'current_total_price' => '50', 'currency' => 'USD', 'created_at' => now()->toIso8601String(), 'line_items' => [],
            'customer' => ['id' => 900 + $id, 'orders_count' => $count, 'total_spent' => '300', 'tags' => '']]);
        app(Engine::class)->trigger($this->store, 'order_paid', $order(1, 5), 'p:1');
        app(Engine::class)->trigger($this->store, 'order_paid', $order(2, 1), 'p:2');
        $this->assertSame(1, InboxItem::count(), 'Only the VIP order notified the team.');

        $owner = $this->member($this->store, 'owner');
        $this->page('/app/automation/workflows/'.$workflow->id, $owner)->assertOk()->assertJsonPath('props.catalog.conditions.segment.label', 'Customer segment')->assertSee('VIP');
    }
}
