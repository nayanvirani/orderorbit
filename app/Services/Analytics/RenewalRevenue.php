<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Subscription renewals: orders a subscription app creates without a checkout, so the web pixel
 * never sees them. They're recorded from the orders/create webhook, and the lines a Growvia offer
 * or bundle added (the same `_oo_offer` / `_oo_bundle` properties the pixel reads) are credited
 * to it. Orders placed through checkout are left to the pixel, so nothing is counted twice.
 */
class RenewalRevenue
{
    public function record(Store $store, array $order): void
    {
        if (! self::isRenewal($order)) {
            return;
        }
        $ref = 'gid://shopify/Order/'.(int) ($order['id'] ?? 0);
        if ($ref === 'gid://shopify/Order/0') {
            return;
        }
        $base = array_filter([
            'store_id' => $store->id,
            'occurred_at' => isset($order['created_at']) ? \Illuminate\Support\Carbon::parse($order['created_at']) : now(),
            'order_ref' => $ref,
            'currency' => strtoupper(substr((string) ($order['currency'] ?? ''), 0, 3)) ?: null,
            'country' => preg_match('/^[A-Z]{2}$/', (string) ($order['shipping_address']['country_code'] ?? $order['billing_address']['country_code'] ?? '')) ? ($order['shipping_address']['country_code'] ?? $order['billing_address']['country_code']) : null,
            'customer_id' => preg_match('/^\d{1,20}$/', (string) ($order['customer']['id'] ?? '')) ? (string) $order['customer']['id'] : null,
            'source' => 'subscription',
        ], fn ($v) => $v !== null);
        $lines = array_slice((array) ($order['line_items'] ?? []), 0, 250);
        if (AnalyticsEvent::where('store_id', $store->id)->where('event', 'order')->where('order_ref', $ref)->exists()) {
            return; // Already recorded (webhooks can arrive twice).
        }

        try {
            AnalyticsEvent::create($base + ['event' => 'order', 'name' => 'subscription_renewal', 'value' => round((float) ($order['total_price'] ?? 0), 2),
                'quantity' => max(1, (int) array_sum(array_map(fn ($l) => (int) ($l['quantity'] ?? 1), $lines)))]);
        } catch (UniqueConstraintViolationException) {
            return; // Already recorded (webhooks can arrive twice).
        }

        $credited = [];
        foreach ($lines as $line) {
            $props = self::properties($line);
            $who = self::handle($props['_oo_offer'] ?? null) ?? self::handle(isset($props['_oo_bundle']) ? explode('|', (string) $props['_oo_bundle'])[0] : null);
            if (! $who) {
                continue;
            }
            $discount = array_sum(array_map(fn ($d) => (float) ($d['amount'] ?? 0), (array) ($line['discount_allocations'] ?? [])));
            $credited[$who]['value'] = ($credited[$who]['value'] ?? 0) + (float) ($line['price'] ?? 0) * (int) ($line['quantity'] ?? 1) - $discount;
            $credited[$who]['quantity'] = ($credited[$who]['quantity'] ?? 0) + (int) ($line['quantity'] ?? 1);
        }
        foreach ($credited as $who => $c) {
            AnalyticsEvent::create($base + ['event' => 'attributed', 'name' => 'growvia:revenue_attributed', 'experience_handle' => $who,
                'value' => round(max(0, $c['value']), 2), 'quantity' => max(1, $c['quantity'])]);
        }
    }

    /** An order a subscription app placed: no checkout behind it, and marked as a subscription or carrying Growvia lines. */
    public static function isRenewal(array $order): bool
    {
        if (! empty($order['checkout_token']) || ! empty($order['checkout_id'])) {
            return false;
        }
        if (str_contains(strtolower((string) ($order['source_name'] ?? '')), 'subscription')) {
            return true;
        }
        foreach ((array) ($order['line_items'] ?? []) as $line) {
            $props = self::properties($line);
            if (isset($props['_oo_offer']) || isset($props['_oo_bundle'])) {
                return true;
            }
        }

        return false;
    }

    private static function properties(array $line): array
    {
        $out = [];
        foreach ((array) ($line['properties'] ?? []) as $p) {
            if (is_array($p) && isset($p['name'])) {
                $out[(string) $p['name']] = $p['value'] ?? null;
            }
        }

        return $out;
    }

    private static function handle(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^[A-Za-z0-9_\-:]{1,64}$/', $v) ? explode(':', $v)[0] : null;
    }
}
