<?php

namespace App\Automation;

use App\Models\Automation\AutomationEmail;
use App\Models\Automation\WorkflowRun;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;

/**
 * Turns Shopify webhooks and OrderOrbit events into workflow triggers. Each trigger carries a
 * key unique to the event, so Shopify's retries and repeated updates never start a workflow twice.
 */
class Triggers
{
    public function __construct(private readonly Engine $engine) {}

    public function webhook(Store $store, string $topic, array $p): void
    {
        if (! $this->engine->available($store)) {
            return;
        }

        switch ($topic) {
            case 'orders/create':
                $context = Context::fromOrder($p);
                $this->engine->trigger($store, 'order_created', $context, 'order_created:'.$p['id']);
                $this->engine->trigger($store, 'product_purchased', $context, 'product_purchased:'.$p['id']);
                break;
            case 'orders/paid':
                $this->engine->trigger($store, 'order_paid', Context::fromOrder($p), 'order_paid:'.$p['id']);
                break;
            case 'orders/fulfilled':
                $this->engine->trigger($store, 'order_fulfilled', Context::fromOrder($p), 'order_fulfilled:'.$p['id']);
                break;
            case 'orders/cancelled':
                $this->engine->trigger($store, 'order_cancelled', Context::fromOrder($p), 'order_cancelled:'.$p['id']);
                break;
            case 'orders/updated':
                // Delivery is reported on the order's fulfillments when the carrier's tracking says so.
                $delivered = collect($p['fulfillments'] ?? [])->contains(fn ($f) => ($f['shipment_status'] ?? null) === 'delivered');
                if ($delivered) {
                    $this->engine->trigger($store, 'order_delivered', Context::fromOrder($p), 'order_delivered:'.$p['id']);
                }
                break;
            case 'refunds/create':
                $context = ['order' => ['id' => (string) ($p['order_id'] ?? ''), 'name' => '', 'created_at' => $p['created_at'] ?? null], 'customer' => null];
                $this->engine->trigger($store, 'refund_created', $context, 'refund_created:'.($p['id'] ?? uniqid()));
                break;
            case 'customers/create':
                $this->engine->trigger($store, 'customer_created', Context::fromCustomer($p), 'customer_created:'.$p['id']);
                $this->rememberTags($store, $p);
                break;
            case 'customers/update':
                $added = $this->addedTags($store, $p);
                foreach ($added as $tag) {
                    $this->engine->trigger($store, 'customer_tag_added', Context::fromCustomer($p), 'customer_tag_added:'.$p['id'].':'.strtolower($tag), ['added_tags' => [$tag]]);
                }
                break;
        }
    }

    /** OrderOrbit events from the web pixel (survey answers, unlocked rewards, accepted upsells…). */
    public function event(Store $store, string $event, ?string $label, ?string $experience): void
    {
        if ($this->engine->available($store)) {
            $this->engine->trigger($store, 'custom_event', Context::fromEvent($event, $label, $experience), 'custom_event:'.$event.':'.uniqid('', true));
        }
    }

    /** Removes a customer's details from automation data (customers/redact). */
    public function forget(Store $store, string $customerId): void
    {
        AutomationEmail::where('store_id', $store->id)->where('customer_id', $customerId)->delete();
        WorkflowRun::where('store_id', $store->id)->where('context', 'like', '%"'.$customerId.'"%')->get()
            ->filter(fn (WorkflowRun $r) => ($r->context['customer']['id'] ?? null) === $customerId)
            ->each(fn (WorkflowRun $r) => $r->forceFill(['context' => array_merge($r->context, ['customer' => null])])->save());
    }

    /** @return list<string> tags this update added, compared with the last update we saw */
    private function addedTags(Store $store, array $p): array
    {
        $key = "automation:tags:{$store->id}:".($p['id'] ?? '');
        $now = array_values(array_filter(array_map('trim', explode(',', (string) ($p['tags'] ?? '')))));
        $before = Cache::get($key);
        Cache::put($key, $now, now()->addDays(180));

        return $before === null ? [] : array_values(array_diff($now, $before));
    }

    private function rememberTags(Store $store, array $p): void
    {
        Cache::put("automation:tags:{$store->id}:".($p['id'] ?? ''), array_values(array_filter(array_map('trim', explode(',', (string) ($p['tags'] ?? ''))))), now()->addDays(180));
    }
}
