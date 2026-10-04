<?php

namespace App\Automation;

use App\Models\Automation\WorkflowRun;
use App\Services\Shopify\AdminApi;

/**
 * Evaluates a condition step's rules against a run's context. Collection membership and "ordered
 * again since the trigger" are looked up from Shopify when the step runs, so they reflect the
 * store at that moment (after a wait, for example).
 */
class Conditions
{
    public function __construct(private readonly AdminApi $api) {}

    public function passes(array $node, WorkflowRun $run): bool
    {
        $results = array_map(fn ($rule) => $this->rule($rule, $run), $node['rules']);

        return $node['match'] === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    private function rule(array $rule, WorkflowRun $run): bool
    {
        $c = $run->context;
        $order = $c['order'] ?? [];
        $customer = $c['customer'] ?? [];
        $value = $rule['value'];
        $list = fn ($v) => array_values(array_filter(array_map(fn ($s) => strtoupper(trim((string) $s)), is_array($v) ? $v : explode(',', (string) $v))));
        $ids = fn ($v) => array_map(fn ($p) => preg_match('/(\d+)$/', (string) ($p['id'] ?? $p), $m) ? $m[1] : '', (array) $v);

        $actual = match ($rule['field']) {
            'order_total' => $order['total'] ?? null,
            'quantity' => $order['quantity'] ?? null,
            'previous_orders' => isset($customer['orders_count']) ? max(0, $customer['orders_count'] - 1) : null,
            'ltv' => $customer['total_spent'] ?? null,
            'days_since_order' => Context::daysSince($order['created_at'] ?? null),
            default => null,
        };

        switch ($rule['field']) {
            case 'order_total':
            case 'quantity':
            case 'previous_orders':
            case 'ltv':
            case 'days_since_order':
                if ($actual === null) {
                    return false;
                }

                return match ($rule['op']) {
                    'gte' => $actual >= $value, 'lte' => $actual <= $value, 'gt' => $actual > $value, 'lt' => $actual < $value, default => abs($actual - $value) < 0.0001,
                };

            case 'product':
                return $this->includes($rule['op'], array_intersect($ids($value), $order['products'] ?? []) !== []);
            case 'variant':
                return $this->includes($rule['op'], array_intersect($list($value), array_map('strtoupper', $order['variants'] ?? [])) !== []);
            case 'sku':
                return $this->includes($rule['op'], array_intersect($list($value), array_map('strtoupper', $order['skus'] ?? [])) !== []);
            case 'collection':
                return $this->includes($rule['op'], array_intersect($ids($value), $this->collections($run)) !== []);
            case 'customer':
                if (! isset($customer['orders_count'])) {
                    return false;
                }

                return $value === 'new' ? $customer['orders_count'] <= 1 : $customer['orders_count'] > 1;
            case 'customer_tag':
                $has = in_array(strtoupper(trim((string) $value)), array_map('strtoupper', $customer['tags'] ?? []), true);

                return $rule['op'] === 'has' ? $has : ! $has;
            case 'country':
            case 'province':
                $field = $order[$rule['field']] ?? '';
                if ($field === '') {
                    return false;
                }
                $in = in_array($field, $list($value), true);

                return $rule['op'] === 'in' ? $in : ! $in;
            case 'shipping_method':
                return $this->includes($rule['op'], str_contains(strtolower($order['shipping_method'] ?? ''), strtolower((string) $value)));
            case 'payment_method':
                return $this->includes($rule['op'], collect($order['payment_methods'] ?? [])->contains(fn ($m) => str_contains(strtolower($m), strtolower((string) $value))));
            case 'fulfillment_status':
                $is = ($order['fulfillment_status'] ?? 'unfulfilled') === $value;

                return $rule['op'] === 'is' ? $is : ! $is;
            case 'ordered_again':
                return ($this->orderedAgain($run) ? 'yes' : 'no') === $value;
            case 'event_property':
                return $this->includes($rule['op'], str_contains(strtolower((string) ($c['event']['label'] ?? '')), strtolower((string) $value)));
        }

        return false;
    }

    private function includes(string $op, bool $found): bool
    {
        return $op === 'not_contains' ? ! $found : $found;
    }

    /** @return list<string> collection ids of the order's products */
    private function collections(WorkflowRun $run): array
    {
        $products = array_slice($run->context['order']['products'] ?? [], 0, 50);
        if (! $products || $run->test) {
            return [];
        }
        $nodes = $this->api->graphql($run->store, 'query ($ids: [ID!]!) { nodes(ids: $ids) { ... on Product { collections(first: 50) { nodes { id } } } } }', [
            'ids' => array_map(fn ($id) => "gid://shopify/Product/{$id}", $products),
        ])['nodes'] ?? [];

        return collect($nodes)->filter()->flatMap(fn ($n) => array_map(fn ($c) => preg_replace('/\D/', '', $c['id']), $n['collections']['nodes'] ?? []))->unique()->values()->all();
    }

    /** Whether the customer placed another order after the one that started this run. */
    private function orderedAgain(WorkflowRun $run): bool
    {
        $customer = $run->context['customer']['id'] ?? null;
        if (! $customer || $run->test) {
            return false;
        }
        $last = $this->api->graphql($run->store, 'query ($id: ID!) { customer(id: $id) { lastOrder { id createdAt } } }', ['id' => "gid://shopify/Customer/{$customer}"])['customer']['lastOrder'] ?? null;
        if (! $last) {
            return false;
        }
        $lastId = preg_replace('/\D/', '', $last['id']);

        return $lastId !== ($run->context['order']['id'] ?? '') && strtotime($last['createdAt']) > $run->created_at->getTimestamp();
    }
}
