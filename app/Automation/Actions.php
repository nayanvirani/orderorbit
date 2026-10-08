<?php

namespace App\Automation;

use App\Models\Automation\AutomationEmail;
use App\Models\Automation\InboxItem;
use App\Models\Automation\Workflow;
use App\Models\Automation\WorkflowRun;
use App\Services\Shopify\AdminApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Runs one action step. Returns [status, message, data]: status is ok or skipped (nothing to
 * act on, e.g. no customer). Throws on a failure worth retrying (Shopify or a webhook said no).
 */
class Actions
{
    public function __construct(private readonly AdminApi $api) {}

    /** @return array{0: string, 1: string, 2: array} */
    public function run(array $node, WorkflowRun $run, int $step): array
    {
        $p = $node['params'];
        $vars = Context::placeholders($run->context, $run->results ?? [], (string) ($run->store->name ?: $run->store->shop_domain));
        $text = fn (string $key) => Context::fill((string) ($p[$key] ?? ''), $vars);
        $order = $run->context['order'] ?? null;
        $customer = $run->context['customer'] ?? null;

        switch ($node['action']) {
            case 'send_email':
                if (empty($customer['email'])) {
                    return ['skipped', 'This customer has no email address.', []];
                }
                // Queued here; orderorbit:send-emails sends it within a minute through the email providers.
                $email = AutomationEmail::create([
                    'store_id' => $run->store_id, 'run_id' => $run->id, 'customer_id' => $customer['id'] ?? null,
                    'to_email' => $customer['email'], 'subject' => $text('subject'), 'body' => $text('body'),
                    'status' => 'queued',
                ]);

                return ['ok', 'Email queued to send: “'.Str::limit($email->subject, 80).'”.', ['email_id' => $email->id]];

            case 'notify':
            case 'create_task':
                $item = InboxItem::create([
                    'store_id' => $run->store_id, 'run_id' => $run->id, 'kind' => $node['action'] === 'notify' ? 'notification' : 'task',
                    'title' => $text('title'), 'body' => $text('body') ?: null,
                    'due_at' => $node['action'] === 'create_task' ? now()->addDays((int) ($p['due_days'] ?? 1)) : null,
                ]);

                return ['ok', ($item->kind === 'task' ? 'Task created: ' : 'Team notified: ').$item->title, ['inbox_id' => $item->id]];

            case 'add_order_tag':
            case 'remove_order_tag':
            case 'add_customer_tag':
            case 'remove_customer_tag':
                $onOrder = str_contains($node['action'], 'order');
                $id = $onOrder ? ($order['id'] ?? null) : ($customer['id'] ?? null);
                if (! $id) {
                    return ['skipped', 'No '.($onOrder ? 'order' : 'customer').' to tag in this run.', []];
                }
                $tags = array_values(array_filter(array_map('trim', explode(',', $text('tags')))));
                $mutation = str_starts_with($node['action'], 'add') ? 'tagsAdd' : 'tagsRemove';
                $result = $this->api->graphql($run->store, "mutation (\$id: ID!, \$tags: [String!]!) { {$mutation}(id: \$id, tags: \$tags) { userErrors { message } } }", [
                    'id' => 'gid://shopify/'.($onOrder ? 'Order' : 'Customer').'/'.$id, 'tags' => $tags,
                ]);
                if ($error = $result[$mutation]['userErrors'][0]['message'] ?? null) {
                    throw new RuntimeException($error);
                }

                return ['ok', (str_starts_with($node['action'], 'add') ? 'Added ' : 'Removed ').'tags '.implode(', ', $tags).($onOrder ? ' on the order.' : ' on the customer.'), ['tags' => $tags]];

            case 'create_discount':
                $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($p['prefix'] ?? '')) ?: 'OO').'-'.strtoupper(Str::random(6));
                $value = (float) ($p['value'] ?? 10);
                $input = [
                    'title' => 'Growvia · '.$run->workflow->name,
                    'code' => $code,
                    'startsAt' => now()->toIso8601String(),
                    'endsAt' => now()->addDays((int) ($p['expires_days'] ?? 30))->toIso8601String(),
                    'usageLimit' => 1,
                    'appliesOncePerCustomer' => true,
                    'customerSelection' => ! empty($customer['id']) ? ['customers' => ['add' => ['gid://shopify/Customer/'.$customer['id']]]] : ['all' => true],
                    'customerGets' => [
                        'value' => ($p['kind'] ?? 'percent') === 'amount' ? ['discountAmount' => ['amount' => $value, 'appliesOnEachItem' => false]] : ['percentage' => min(100, $value) / 100],
                        'items' => ['all' => true],
                    ],
                ];
                $result = $this->api->graphql($run->store, 'mutation ($d: DiscountCodeBasicInput!) { discountCodeBasicCreate(basicCodeDiscount: $d) { codeDiscountNode { id } userErrors { message } } }', ['d' => $input]);
                if ($error = $result['discountCodeBasicCreate']['userErrors'][0]['message'] ?? null) {
                    throw new RuntimeException($error);
                }

                return ['ok', "Created discount code {$code}.", ['discount_code' => $code]];

            case 'webhook':
                $response = Http::timeout(10)->acceptJson()->post($p['url'], [
                    'source' => 'orderorbit-space', 'shop' => $run->store->shop_domain,
                    'workflow' => ['id' => $run->workflow->handle, 'name' => $run->workflow->name], 'run_id' => $run->id,
                    'trigger' => $run->trigger, 'order' => $order, 'customer' => $customer ? array_diff_key($customer, ['email' => 1]) : null,
                    'event' => $run->context['event'] ?? null,
                ]);
                if ($response->failed()) {
                    throw new RuntimeException('The webhook answered HTTP '.$response->status().'.');
                }

                return ['ok', 'Sent to '.parse_url($p['url'], PHP_URL_HOST).' (HTTP '.$response->status().').', []];

            case 'trigger_workflow':
                $target = Workflow::where('store_id', $run->store_id)->where('handle', $p['workflow'])->first();
                if (! $target || $target->status !== 'enabled' || ! $target->published_version_id) {
                    return ['skipped', 'The workflow to start is missing or not enabled.', []];
                }
                if ($run->depth >= 3 || $target->id === $run->workflow_id) {
                    return ['skipped', 'Not started: workflows can start other workflows at most 3 levels deep, and never themselves.', []];
                }
                $child = app(Engine::class)->start($run->store, $target, $run->context, "run:{$run->id}:{$step}", $run->depth + 1);

                return ['ok', 'Started “'.$target->name.'”.', ['run_id' => $child?->id]];
        }

        return ['skipped', 'Unknown action.', []];
    }

    /** What an action would do, for test runs (nothing is changed). */
    public function describe(array $node, WorkflowRun $run): string
    {
        $vars = Context::placeholders($run->context, $run->results ?? [], (string) ($run->store->name ?: $run->store->shop_domain));
        $p = $node['params'];

        return 'Would '.match ($node['action']) {
            'send_email' => 'send the email “'.Context::fill((string) $p['subject'], $vars).'”',
            'notify' => 'notify your team: '.Context::fill((string) $p['title'], $vars),
            'create_task' => 'create the task: '.Context::fill((string) $p['title'], $vars),
            'add_order_tag' => 'add order tags '.$p['tags'],
            'remove_order_tag' => 'remove order tags '.$p['tags'],
            'add_customer_tag' => 'add customer tags '.$p['tags'],
            'remove_customer_tag' => 'remove customer tags '.$p['tags'],
            'create_discount' => 'create a single-use '.(($p['kind'] ?? 'percent') === 'amount' ? $p['value'].' off' : $p['value'].'% off').' code',
            'webhook' => 'send the order to '.parse_url((string) $p['url'], PHP_URL_HOST),
            'trigger_workflow' => 'start the workflow '.$p['workflow'],
            default => $node['action'],
        }.'.';
    }
}
