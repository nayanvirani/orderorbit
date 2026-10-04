<?php

namespace Tests\Feature;

use App\Automation\Context;
use App\Automation\Definition;
use App\Automation\Engine;
use App\Automation\WorkflowManager;
use App\Models\Automation\AutomationEmail;
use App\Models\Automation\InboxItem;
use App\Models\Automation\Workflow;
use App\Models\Automation\WorkflowRun;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    /** @var list<array> Admin API calls the engine made */
    private array $calls = [];

    private bool $tagsFail = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(function (Request $request) {
            $query = $request['query'] ?? '';
            if (str_contains($request->url(), '/admin/oauth/access_token')) {
                return Http::response(['access_token' => 'shpat_new', 'scope' => 'read_products', 'expires_in' => 3600, 'refresh_token' => 'r', 'refresh_token_expires_in' => 7776000]);
            }
            if (str_contains($request->url(), 'hooks.example.com')) {
                $this->calls[] = ['webhook', $request->data()];

                return Http::response(['ok' => true]);
            }
            if (str_contains($query, 'tagsAdd') || str_contains($query, 'tagsRemove')) {
                $this->calls[] = [str_contains($query, 'tagsAdd') ? 'tagsAdd' : 'tagsRemove', json_decode(json_encode($request['variables']), true)];

                return $this->tagsFail ? Http::response(['errors' => [['message' => 'Throttled']]], 200) : Http::response(['data' => [str_contains($query, 'tagsAdd') ? 'tagsAdd' : 'tagsRemove' => ['userErrors' => []]]]);
            }
            if (str_contains($query, 'discountCodeBasicCreate')) {
                $this->calls[] = ['discount', json_decode(json_encode($request['variables']), true)];

                return Http::response(['data' => ['discountCodeBasicCreate' => ['codeDiscountNode' => ['id' => 'gid://shopify/DiscountCodeNode/1'], 'userErrors' => []]]]);
            }
            if (str_contains($query, 'lastOrder')) {
                return Http::response(['data' => ['customer' => ['lastOrder' => ['id' => 'gid://shopify/Order/5001', 'createdAt' => now()->subDays(100)->toIso8601String()]]]]);
            }

            return Http::response(['data' => []]);
        });
    }

    private function store(string $plan = 'scale'): Store
    {
        return $this->installedStore(['plan' => $plan]);
    }

    private function workflow(Store $store, array $definition, string $name = 'Test workflow'): Workflow
    {
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create($store, null, null, $name);
        $this->assertSame([], $manager->saveDraft($workflow, $definition, $name));
        $manager->publish($workflow->fresh(), null);

        return $workflow->fresh();
    }

    private function order(array $over = []): array
    {
        return array_replace_recursive([
            'id' => 5001, 'name' => '#1052', 'current_total_price' => '180.00', 'currency' => 'USD', 'created_at' => now()->toIso8601String(),
            'line_items' => [['product_id' => 7, 'variant_id' => 70, 'sku' => 'SERUM', 'title' => 'Glow Serum', 'quantity' => 2]],
            'shipping_address' => ['country_code' => 'US'], 'payment_gateway_names' => ['shopify_payments'], 'tags' => '',
            'customer' => ['id' => 900, 'tags' => 'newsletter', 'orders_count' => 1, 'total_spent' => '180.00'],
        ], $over);
    }

    public function test_definitions_validate_and_compile_branches(): void
    {
        [$def, $errors] = Definition::normalize(['trigger' => 'nope', 'steps' => [
            ['type' => 'action', 'action' => 'webhook', 'params' => ['url' => 'http://insecure.example.com']],
            ['type' => 'wait', 'amount' => 0, 'unit' => 'days'],
            ['type' => 'condition', 'rules' => []],
        ]]);
        $this->assertSame(['trigger', 'steps.0.params.url', 'steps.1.amount', 'steps.2.rules'], array_keys($errors));

        $program = Definition::compile([
            ['type' => 'condition', 'match' => 'all', 'rules' => [['field' => 'order_total', 'op' => 'gte', 'value' => 100]],
                'then' => [['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'Big']]],
                'else' => [['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'Small']]]],
            ['type' => 'wait', 'amount' => 1, 'unit' => 'days'],
        ]);
        $this->assertSame(['cond', 'action', 'jump', 'action', 'wait'], array_column($program, 't'));
        $this->assertSame(3, $program[0]['else']);
        $this->assertSame(4, $program[2]['to']);

        // Every template is a valid workflow (the product pickers are filled in by the merchant).
        foreach (Definition::templates() as $key => $template) {
            $template['trigger_config'] = ['products' => [['id' => 'gid://shopify/Product/1', 'title' => 'A']]];
            $this->assertSame([], Definition::normalize($template)[1], $key);
        }
    }

    public function test_an_order_runs_conditions_actions_and_waits_once(): void
    {
        $store = $this->store();
        $workflow = $this->workflow($store, ['trigger' => 'order_paid', 'steps' => [
            ['type' => 'condition', 'match' => 'all', 'rules' => [['field' => 'order_total', 'op' => 'gte', 'value' => 100], ['field' => 'customer', 'op' => 'is', 'value' => 'new']],
                'then' => [
                    ['type' => 'action', 'action' => 'add_customer_tag', 'params' => ['tags' => 'vip, first-order']],
                    ['type' => 'action', 'action' => 'create_discount', 'params' => ['kind' => 'percent', 'value' => 15, 'expires_days' => 30, 'prefix' => 'VIP']],
                ],
                'else' => [['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'Small order']]]],
            ['type' => 'wait', 'amount' => 3, 'unit' => 'days'],
            ['type' => 'action', 'action' => 'send_email', 'params' => ['subject' => 'Thanks for {{order_name}}', 'body' => 'Here is your code: {{discount_code}} — {{shop_name}}']],
            ['type' => 'action', 'action' => 'webhook', 'params' => ['url' => 'https://hooks.example.com/oo']],
        ]]);

        $engine = app(Engine::class);
        $context = Context::fromOrder($this->order());
        $this->assertSame(1, $engine->trigger($store, 'order_paid', $context, 'order_paid:5001'));
        $this->assertSame(0, $engine->trigger($store, 'order_paid', $context, 'order_paid:5001'), 'Shopify retries never start it twice.');

        $run = WorkflowRun::sole();
        $this->assertSame(['waiting', 'Order #1052'], [$run->status, $run->subject]);
        $this->assertSame(['tagsAdd', ['id' => 'gid://shopify/Customer/900', 'tags' => ['vip', 'first-order']]], $this->calls[0]);
        $code = $run->results['2']['discount_code'];
        $this->assertStringStartsWith('VIP-', $code);
        $this->assertSame(['gid://shopify/Customer/900'], $this->calls[1][1]['d']['customerSelection']['customers']['add']);
        $this->assertSame(0.15, $this->calls[1][1]['d']['customerGets']['value']['percentage']);
        $this->assertSame(0, InboxItem::count(), 'The else branch was skipped.');

        // Nothing happens before the wait is over…
        $this->assertSame(0, $engine->tick());
        // …then the worker resumes it.
        $this->travel(3)->days();
        $this->travel(1)->minutes();
        $this->assertSame(1, $engine->tick());
        $run->refresh();
        $this->assertSame('completed', $run->status);
        $email = AutomationEmail::sole();
        $this->assertSame(['Thanks for #1052', 'waiting_for_provider', '900'], [$email->subject, $email->status, $email->customer_id]);
        $this->assertStringContainsString($code, $email->body);
        $this->assertSame('webhook', end($this->calls)[0]);
        $this->assertArrayNotHasKey('email', end($this->calls)[1]['customer'], 'Webhooks never carry the customer\'s email.');
        $this->assertSame(['condition', 'add_customer_tag', 'create_discount', 'wait', 'send_email', 'webhook', 'end'], $run->logs()->orderBy('id')->pluck('kind')->all());
    }

    public function test_failed_actions_retry_then_fail_and_are_never_repeated(): void
    {
        $store = $this->store();
        $this->workflow($store, ['trigger' => 'order_created', 'steps' => [
            ['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'New order {{order_name}}']],
            ['type' => 'action', 'action' => 'add_order_tag', 'params' => ['tags' => 'reviewed']],
        ]]);
        $this->tagsFail = true;
        $engine = app(Engine::class);
        $engine->trigger($store, 'order_created', Context::fromOrder($this->order()), 'order_created:5001');

        $run = WorkflowRun::sole();
        $this->assertSame(['waiting', 1], [$run->status, $run->attempts]);
        $this->travel(6)->minutes();
        $engine->tick();
        $this->assertSame(2, $run->fresh()->attempts);
        $this->travel(31)->minutes();
        $engine->tick();
        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Throttled', $run->error);
        $this->assertSame(1, InboxItem::count(), 'The notification before the failing step was created once, not on every retry.');
        $this->assertSame('New order #1052', InboxItem::sole()->title);
    }

    public function test_triggers_match_their_settings(): void
    {
        $store = $this->store();
        $engine = app(Engine::class);
        $this->workflow($store, ['trigger' => 'product_purchased', 'trigger_config' => ['products' => [['id' => 'gid://shopify/Product/7', 'title' => 'Serum']]],
            'steps' => [['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'Serum sold']]]]);
        $this->workflow($store, ['trigger' => 'customer_tag_added', 'trigger_config' => ['tag' => 'VIP'],
            'steps' => [['type' => 'action', 'action' => 'create_task', 'params' => ['title' => 'Call the VIP', 'due_days' => 2]]]]);

        $this->assertSame(0, $engine->trigger($store, 'product_purchased', Context::fromOrder($this->order(['line_items' => [['product_id' => 8, 'quantity' => 1]]])), 'p:1'));
        $this->assertSame(1, $engine->trigger($store, 'product_purchased', Context::fromOrder($this->order()), 'p:2'));
        $this->assertSame(0, $engine->trigger($store, 'customer_tag_added', Context::fromCustomer(['id' => 1]), 't:1', ['added_tags' => ['other']]));
        $this->assertSame(1, $engine->trigger($store, 'customer_tag_added', Context::fromCustomer(['id' => 1]), 't:2', ['added_tags' => ['vip']]));
        $this->assertTrue(InboxItem::where('kind', 'task')->sole()->due_at->isSameDay(now()->addDays(2)));
    }

    public function test_win_back_checks_for_a_newer_order_after_the_wait(): void
    {
        $store = $this->store();
        $this->workflow($store, ['trigger' => 'order_paid', 'steps' => [
            ['type' => 'wait', 'amount' => 90, 'unit' => 'days'],
            ['type' => 'condition', 'match' => 'all', 'rules' => [['field' => 'ordered_again', 'op' => 'is', 'value' => 'no']],
                'then' => [['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'Win back']]], 'else' => []],
        ]]);
        $engine = app(Engine::class);
        $engine->trigger($store, 'order_paid', Context::fromOrder($this->order()), 'order_paid:5001');
        $this->travel(91)->days();
        $engine->tick();
        $this->assertSame('Win back', InboxItem::sole()->title, 'The customer\'s latest order is still the one that started the run.');
    }

    public function test_test_runs_change_nothing_and_plan_gates_publishing(): void
    {
        $store = $this->store('growth');
        $owner = $this->member($store, 'owner');
        $manager = app(WorkflowManager::class);
        $workflow = $manager->create($store, 'vip-customer', null);
        $this->assertSame('order_paid', $workflow->trigger);

        $run = $manager->test($workflow);
        $this->assertTrue($run->test);
        $this->assertSame('completed', $run->status);
        $this->assertSame([], $this->calls, 'Nothing reaches Shopify in a test.');
        $this->assertSame(0, InboxItem::count() + AutomationEmail::count());
        $this->assertStringStartsWith('Would ', $run->logs()->where('kind', 'add_customer_tag')->value('message') ?? 'Would (condition not met)');

        // Growth can build and test, but workflows only run on Scale.
        $this->post('/app/automation/workflows/'.$workflow->id, ['name' => 'VIP', 'action' => 'publish', 'definition' => json_encode($workflow->draft)], $this->as($owner))
            ->assertOk()->assertSee('Workflows run on the Scale plan');
        $this->assertNull($workflow->fresh()->published_version_id);
        $this->assertSame(0, app(Engine::class)->trigger($store, 'order_paid', Context::fromOrder($this->order()), 'x'));
    }

    public function test_screens(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        $this->get('/app/automation', $this->as($owner))->assertOk()->assertSee('Follow up')->assertSee('Start from a template');
        $this->get('/app/automation/templates', $this->as($owner))->assertOk()->assertSee('Review request')->assertSee('Win-back')->assertSee('Cancellation follow-up');
        $this->post('/app/automation/workflows', ['template' => 'review-request'], $this->as($owner))->assertRedirectContains('/app/automation/workflows/');
        $workflow = Workflow::sole();
        $this->get('/app/automation/workflows/'.$workflow->id, $this->as($owner))->assertOk()->assertSee('data-canvas', false)->assertSee('Test with a sample order');

        // Invalid steps come back with errors; the draft is kept.
        $bad = $workflow->draft;
        $bad['steps'][] = ['type' => 'action', 'action' => 'webhook', 'params' => ['url' => 'ftp://nope']];
        $this->post('/app/automation/workflows/'.$workflow->id, ['name' => 'Reviews', 'action' => 'publish', 'definition' => json_encode($bad)], $this->as($owner))
            ->assertOk()->assertSee('Fix the highlighted steps')->assertSee('Use a full https:// address.');
        $this->assertSame('Reviews', $workflow->fresh()->name);

        $good = $workflow->draft;
        $this->post('/app/automation/workflows/'.$workflow->id, ['name' => 'Reviews', 'action' => 'publish', 'definition' => json_encode($good)], $this->as($owner))->assertRedirectContains('published');
        $this->assertSame('enabled', $workflow->fresh()->status);

        $this->post('/app/automation/workflows/'.$workflow->id, ['name' => 'Reviews', 'action' => 'test', 'definition' => json_encode($good)], $this->as($owner))->assertRedirectContains('/app/automation/runs/');
        $run = WorkflowRun::where('test', true)->sole();
        $this->get('/app/automation/runs/'.$run->id, $this->as($owner))->assertOk()->assertSee('Test run')->assertSee('Would wait 5 days');
        $this->get('/app/automation/runs', $this->as($owner))->assertOk()->assertSee('#'.$run->id);
        $this->get('/app/automation/inbox', $this->as($owner))->assertOk()->assertSee('Nothing to do');
        $this->get('/app/automation/emails', $this->as($owner))->assertOk()->assertSee('Email sending isn\'t connected yet', false);
        $this->post('/app/automation/workflows/'.$workflow->id.'/toggle', [], $this->as($owner))->assertRedirect();
        $this->assertSame('disabled', $workflow->fresh()->status);
    }
}
