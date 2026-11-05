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

    private bool $noOrders = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        // Webhook hosts resolve to a public address without a real DNS lookup.
        \App\Support\PublicUrl::$resolver = fn () => ['93.184.215.14'];
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
            // The customer's real totals (order webhooks leave them out) and the latest order for tests.
            if (str_contains($query, 'customer(id: $id) { id email')) {
                return Http::response(['data' => ['customer' => ['id' => 'gid://shopify/Customer/900', 'email' => 'ana@example.com', 'firstName' => 'Ana', 'lastName' => 'B',
                    'tags' => ['newsletter'], 'numberOfOrders' => 4, 'amountSpent' => ['amount' => '420.00']]]]);
            }
            if (str_contains($query, 'orders(first: 1')) {
                if ($this->noOrders) {
                    return Http::response(['data' => ['orders' => ['nodes' => []]]]);
                }

                return Http::response(['data' => ['orders' => ['nodes' => [[
                    'id' => 'gid://shopify/Order/7001', 'name' => '#1077', 'createdAt' => now()->toIso8601String(), 'tags' => [], 'currencyCode' => 'USD',
                    'displayFulfillmentStatus' => 'UNFULFILLED', 'paymentGatewayNames' => ['manual'], 'currentTotalPriceSet' => ['shopMoney' => ['amount' => '64.00']],
                    'shippingAddress' => ['countryCodeV2' => 'IN', 'provinceCode' => 'GJ'], 'shippingLines' => ['nodes' => []],
                    'lineItems' => ['nodes' => [['title' => 'Ski Wax', 'variantTitle' => null, 'quantity' => 1, 'sku' => 'W1', 'product' => ['id' => 'gid://shopify/Product/8'], 'variant' => ['id' => 'gid://shopify/ProductVariant/80']]]],
                    'customer' => ['id' => 'gid://shopify/Customer/900', 'email' => 'ana@example.com', 'firstName' => 'Ana', 'lastName' => 'B', 'tags' => [], 'numberOfOrders' => 4, 'amountSpent' => ['amount' => '420.00']],
                ]]]]]);
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
            'customer' => ['id' => 900, 'email' => 'ana@example.com', 'tags' => 'newsletter', 'orders_count' => 1, 'total_spent' => '180.00'],
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
            $template['trigger_config'] = array_filter($template['trigger_config'] ?? []) + ['products' => [['id' => 'gid://shopify/Product/1', 'title' => 'A']]];
            // The merchant fills in their own webhook address.
            array_walk($template['steps'], function (&$s) { if (($s['action'] ?? null) === 'webhook') { $s['params']['url'] = 'https://hooks.example.com/crm'; } });
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
        $this->assertSame(['Thanks for #1052', 'queued', '900'], [$email->subject, $email->status, $email->customer_id]);
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

        // A person can retry it once the problem is fixed; finished steps don't run again.
        $this->tagsFail = false;
        $store->forceFill(['access_token_expires_at' => now()->addHour()])->save();
        $owner = $this->member($store, 'owner');
        $this->page('/app/automation/runs/'.$run->id, $owner)->assertJsonPath('props.run.can_retry', true)->assertSee('order_created:5001');
        $this->post('/app/automation/runs/'.$run->id.'/retry', [], $this->as($owner))->assertRedirectContains('notice=retried');
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(1, InboxItem::count());
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

    public function test_customer_totals_missing_from_the_webhook_are_read_from_shopify(): void
    {
        $store = $this->store();
        $this->workflow($store, ['trigger' => 'order_paid', 'steps' => [
            ['type' => 'condition', 'match' => 'all', 'rules' => [['field' => 'ltv', 'op' => 'gte', 'value' => 150]],
                'then' => [['type' => 'action', 'action' => 'add_customer_tag', 'params' => ['tags' => 'vip']]], 'else' => []],
        ]]);
        // As newer order webhooks send it: the customer without total spent or order count.
        $order = $this->order();
        unset($order['customer']['total_spent'], $order['customer']['orders_count']);
        app(Engine::class)->trigger($store, 'order_paid', Context::fromOrder($order), 'order_paid:5001');

        $run = WorkflowRun::sole();
        $this->assertEquals(420, $run->context['customer']['total_spent']);
        $this->assertSame(4, $run->context['customer']['orders_count']);
        $this->assertSame('tagsAdd', $this->calls[0][0] ?? null, 'Lifetime spend 420 ≥ 150: the Then branch ran.');
    }

    public function test_a_test_run_uses_the_latest_real_order(): void
    {
        $store = $this->store();
        $workflow = app(WorkflowManager::class)->create($store, 'vip-customer', null);
        $run = app(WorkflowManager::class)->test($workflow);
        $this->assertSame('#1077', $run->context['order']['name']);
        $this->assertEquals(420, $run->context['customer']['total_spent']);
        $this->assertSame([], $this->calls, 'Nothing reaches Shopify in a test.');

        // A chosen order, by its number; an unknown number says so.
        $run = app(WorkflowManager::class)->test($workflow, null, '1077');
        $this->assertSame('#1077', $run->context['order']['name']);
        $this->assertSame(['8'], $run->context['order']['products']);
        $this->noOrders = true;
        $this->expectExceptionMessage('Order #9999 wasn\'t found');
        app(WorkflowManager::class)->test($workflow, null, '#9999');
    }

    public function test_variant_ids_match_whether_typed_short_or_as_gids(): void
    {
        $store = $this->store();
        $this->workflow($store, ['trigger' => 'order_paid', 'steps' => [
            ['type' => 'condition', 'match' => 'all', 'rules' => [['field' => 'variant', 'op' => 'contains', 'value' => 'gid://shopify/ProductVariant/70, 999']],
                'then' => [['type' => 'action', 'action' => 'add_customer_tag', 'params' => ['tags' => 'serum']]], 'else' => []],
        ]]);
        app(Engine::class)->trigger($store, 'order_paid', Context::fromOrder($this->order()), 'order_paid:5001');
        $this->assertSame('tagsAdd', $this->calls[0][0] ?? null);
    }

    public function test_test_runs_change_nothing_and_plan_gates_publishing(): void
    {
        $store = $this->store('starter');
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

        // Starter can build and test it, but if / else branching needs a higher plan to run.
        $this->send('/app/automation/workflows/'.$workflow->id, ['name' => 'VIP', 'action' => 'publish', 'definition' => $workflow->draft], $owner)
            ->assertOk()->assertJsonPath('component', 'automation/editor')->assertJsonPath('props.banner', 'This workflow uses if / else branching, which isn\'t on this store\'s plan. Upgrade your plan to publish it.');
        $this->assertNull($workflow->fresh()->published_version_id);
        $this->assertSame(0, app(Engine::class)->trigger($store, 'order_paid', Context::fromOrder($this->order()), 'x'));
    }

    public function test_screens(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        $this->page('/app/automation', $owner)->assertOk()->assertJsonPath('component', 'automation/index')->assertJsonPath('props.workflows', []);
        $names = array_column($this->page('/app/automation/templates', $owner)->assertOk()->json('props.templates'), 'name');
        $this->assertNotContains('Review request', $names, 'Review apps already send these.');
        $this->assertContains('Second-order thank-you', $names);
        $this->assertContains('Cancellation follow-up', $names);
        $this->send('/app/automation/workflows', ['template' => 'product-education'], $owner)->assertOk()->assertJsonPath('notice.message', 'Draft created.');
        $workflow = Workflow::sole();
        $this->page('/app/automation/workflows/'.$workflow->id, $owner)->assertOk()->assertJsonPath('props.workflow.draft.trigger', $workflow->draft['trigger'])->assertJsonPath('props.catalog.triggers.order_paid.label', 'Order paid');

        // Invalid steps come back with errors; the draft is kept.
        $bad = $workflow->draft;
        $bad['steps'][] = ['type' => 'action', 'action' => 'webhook', 'params' => ['url' => 'ftp://nope']];
        $this->send('/app/automation/workflows/'.$workflow->id, ['name' => 'Reviews', 'action' => 'publish', 'definition' => $bad], $owner)
            ->assertStatus(422)->assertJsonPath('props.banner', 'Draft saved. Fix the highlighted steps before you publish.')->assertSee('Use a full, public https:\/\/ address.', false);
        $this->assertSame('Reviews', $workflow->fresh()->name);

        $good = $workflow->draft;
        $this->post('/app/automation/workflows/'.$workflow->id, ['name' => 'Reviews', 'action' => 'publish', 'definition' => json_encode($good)], $this->as($owner))->assertRedirectContains('published');
        $this->assertSame('enabled', $workflow->fresh()->status);

        $this->post('/app/automation/workflows/'.$workflow->id, ['name' => 'Reviews', 'action' => 'test', 'definition' => json_encode($good)], $this->as($owner))->assertRedirectContains('/app/automation/runs/');
        $run = WorkflowRun::where('test', true)->sole();
        $this->page('/app/automation/runs/'.$run->id, $owner)->assertOk()->assertJsonPath('props.run.test', true)->assertSee('Would wait 3 days');
        $this->page('/app/automation/runs', $owner)->assertOk()->assertJsonPath('props.runs.data.0.id', $run->id);
        $this->page('/app/automation/inbox', $owner)->assertOk()->assertJsonPath('props.open', []);
        $this->page('/app/automation/emails', $owner)->assertOk()->assertJsonPath('props.emails.data', []);
        $this->post('/app/automation/workflows/'.$workflow->id.'/toggle', [], $this->as($owner))->assertRedirect();
        $this->assertSame('disabled', $workflow->fresh()->status);
    }

    public function test_the_flow_editor_keeps_the_saved_format_and_adds_names_and_time_windows(): void
    {
        // Existing definitions come back unchanged: names and windows are only added when set.
        foreach (Definition::templates() as $key => $template) {
            $template['trigger_config'] = ['products' => [['id' => 'gid://shopify/Product/1', 'title' => 'A']]];
            [$once] = Definition::normalize($template);
            $this->assertSame($once, Definition::normalize($once)[0], $key);
            $this->assertStringNotContainsString('"label"', json_encode($once), $key);
        }

        [$def, $errors] = Definition::normalize(['trigger' => 'order_paid', 'steps' => [
            ['type' => 'condition', 'label' => '  Is this a big spender?  ', 'match' => 'all', 'rules' => [['field' => 'ltv', 'op' => 'gte', 'value' => 300]],
                'then' => [['type' => 'wait', 'amount' => 1, 'unit' => 'days', 'window' => ['from' => '09:00', 'to' => '20:00'], 'label' => '']], 'else' => []],
            ['type' => 'wait', 'amount' => 1, 'unit' => 'hours', 'window' => ['from' => '20:00', 'to' => '09:00']],
        ]]);
        $this->assertSame(['steps.1.window'], array_keys($errors), 'The window must start before it ends.');
        $this->assertSame('Is this a big spender?', $def['steps'][0]['label']);
        $this->assertSame(['type' => 'wait', 'amount' => 1, 'unit' => 'days', 'window' => ['from' => '09:00', 'to' => '20:00']], $def['steps'][0]['then'][0]);
        $this->assertSame(['from' => '09:00', 'to' => '20:00'], Definition::compile($def['steps'])[1]['window']);

        // A wait ending outside the hours continues when they next start, in the store's time zone.
        $ny = 'America/New_York';
        $at = fn (string $local) => Definition::inWindow(\Illuminate\Support\Carbon::parse($local, $ny), ['from' => '09:00', 'to' => '20:00'], $ny)->setTimezone($ny)->format('Y-m-d H:i');
        $this->assertSame('2026-11-05 09:00', $at('2026-11-05 06:30'));
        $this->assertSame('2026-11-05 14:15', $at('2026-11-05 14:15'));
        $this->assertSame('2026-11-06 09:00', $at('2026-11-05 21:00'));
    }

    public function test_a_wait_with_hours_resumes_inside_them(): void
    {
        $store = $this->store();
        $store->forceFill(['timezone' => 'UTC'])->save();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-05 18:00', 'UTC'));
        $this->workflow($store, ['trigger' => 'order_paid', 'steps' => [
            ['type' => 'wait', 'amount' => 3, 'unit' => 'hours', 'window' => ['from' => '09:00', 'to' => '20:00']],
            ['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'Morning']],
        ]]);
        app(Engine::class)->trigger($store, 'order_paid', Context::fromOrder($this->order()), 'order_paid:5001');
        $this->assertSame('2026-11-06 09:00', WorkflowRun::sole()->resume_at->format('Y-m-d H:i'), '21:00 is after hours, so it waits for 09:00.');
    }

    public function test_the_flow_editor_is_released_with_its_flag(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        $workflow = $this->workflow($store, ['trigger' => 'order_paid', 'steps' => [['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'Paid']]]]);
        $this->page('/app/automation/workflows/'.$workflow->id, $owner)->assertJsonPath('component', 'automation/editor');

        \App\Models\FeatureFlag::create(['key' => 'flow_editor', 'mode' => 'stores', 'store_ids' => [$store->id], 'enabled' => true]);
        \App\Support\Features::forget();
        $this->page('/app/automation/workflows/'.$workflow->id, $owner)->assertOk()->assertJsonPath('component', 'automation/flow')
            ->assertJsonPath('props.live.version', 1)->assertJsonPath('props.gates.branching', true)->assertJsonPath('props.stats.runs', 0);

        $draft = $workflow->draft;
        $draft['steps'][0]['label'] = 'Tell the team';
        $this->post('/app/automation/workflows/'.$workflow->id, ['name' => 'Paid orders', 'action' => 'save', 'definition' => json_encode($draft)], $this->as($owner))->assertRedirectContains('saved');
        $this->assertSame('Tell the team', $workflow->fresh()->draft['steps'][0]['label']);
    }

    public function test_the_redesigned_list_templates_and_run_history(): void
    {
        $store = $this->store();
        $owner = $this->member($store, 'owner');
        \App\Models\FeatureFlag::create(['key' => 'flow_editor', 'mode' => 'stores', 'store_ids' => [$store->id], 'enabled' => true]);
        \App\Support\Features::forget();
        $this->send('/app/automation/workflows', ['template' => 'vip-customer'], $owner);
        $vip = Workflow::sole();
        app(WorkflowManager::class)->publish($vip, null);

        $this->page('/app/automation', $owner)->assertOk()->assertJsonPath('component', 'automation/flows')
            ->assertJsonPath('props.workflows.0.pills.0', ['kind' => 'trigger', 'label' => 'Order paid'])
            ->assertJsonPath('props.workflows.0.pills.1', ['kind' => 'condition', 'label' => 'Customer lifetime spend?'])
            ->assertJsonPath('props.workflows.0.pills.2.kind', 'action')
            ->assertJsonPath('props.plan.name', 'Scale')
            ->assertJsonPath('shared.nav.groups.0.items.2.label', 'Run history');
        $templates = collect($this->page('/app/automation/templates', $owner)->assertJsonPath('component', 'automation/flow-templates')->json('props.templates'))->keyBy('key');
        $this->assertTrue($templates['vip-customer']['in_use']);
        $this->assertFalse($templates['reorder-reminder']['in_use']);
        $this->assertSame('growvia', $templates['survey-to-crm']['category']);

        // A small order doesn't match: the run shows the condition, then the Then steps it never reached.
        app(Engine::class)->trigger($store, 'order_paid', Context::fromOrder($this->order(['customer' => ['total_spent' => '40.00']])), 'order_paid:5001');
        $run = WorkflowRun::sole();
        $steps = $this->page('/app/automation/runs/'.$run->id, $owner)->assertOk()->assertJsonPath('component', 'automation/flow-runs')
            ->assertJsonPath('props.run.id', $run->id)->assertJsonPath('props.runs.data.0.id', $run->id)->json('props.run.steps');
        $this->assertSame(['no_match', 'not_reached', 'not_reached', 'not_reached', 'not_reached', 'finished'], array_column($steps, 'status'));
        $this->assertStringEndsWith('The Otherwise branch is empty, so the run ended.', $steps[0]['message']);
        $this->assertSame('Add customer tags', $steps[1]['message']);
    }
}
