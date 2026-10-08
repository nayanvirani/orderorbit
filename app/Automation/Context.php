<?php

namespace App\Automation;

use Illuminate\Support\Carbon;

/**
 * The facts a run works with, taken from Shopify's webhook payloads when it starts: the order,
 * the customer and, for Growvia events, the event. Only what conditions and actions need is
 * kept. Fields Shopify withholds (addresses and contact details need extra protected customer
 * data access) are simply missing, and conditions on them don't match.
 */
class Context
{
    public static function fromOrder(array $o): array
    {
        $lines = (array) ($o['line_items'] ?? []);
        $address = $o['shipping_address'] ?? $o['billing_address'] ?? [];
        $customer = (array) ($o['customer'] ?? []);
        $ordersCount = $customer['orders_count'] ?? $customer['number_of_orders'] ?? null;

        return [
            'order' => [
                'id' => (string) ($o['id'] ?? ''),
                'name' => (string) ($o['name'] ?? ''),
                'total' => (float) ($o['current_total_price'] ?? $o['total_price'] ?? 0),
                'currency' => (string) ($o['currency'] ?? ''),
                'quantity' => array_sum(array_map(fn ($l) => (int) ($l['quantity'] ?? 0), $lines)),
                'created_at' => (string) ($o['created_at'] ?? now()->toIso8601String()),
                'products' => array_values(array_unique(array_filter(array_map(fn ($l) => (string) ($l['product_id'] ?? ''), $lines)))),
                'variants' => array_values(array_unique(array_filter(array_map(fn ($l) => (string) ($l['variant_id'] ?? ''), $lines)))),
                'skus' => array_values(array_filter(array_map(fn ($l) => (string) ($l['sku'] ?? ''), $lines))),
                'titles' => array_values(array_unique(array_filter(array_map(fn ($l) => (string) ($l['title'] ?? ''), $lines)))),
                'shipping_method' => (string) ($o['shipping_lines'][0]['title'] ?? ''),
                'payment_methods' => array_values((array) ($o['payment_gateway_names'] ?? [])),
                'fulfillment_status' => match ($o['fulfillment_status'] ?? null) {
                    'fulfilled' => 'fulfilled', 'partial' => 'partial', default => 'unfulfilled',
                },
                'country' => strtoupper((string) ($address['country_code'] ?? '')),
                'province' => strtoupper((string) ($address['province_code'] ?? '')),
                'tags' => self::tags($o['tags'] ?? ''),
            ],
            'customer' => $customer ? [
                'id' => (string) ($customer['id'] ?? ''),
                'email' => $customer['email'] ?? $o['email'] ?? null,
                'tags' => self::tags($customer['tags'] ?? ''),
                'orders_count' => $ordersCount !== null ? (int) $ordersCount : null,
                'total_spent' => isset($customer['total_spent']) ? (float) $customer['total_spent'] : null,
            ] : null,
        ];
    }

    public static function fromCustomer(array $c): array
    {
        return [
            'order' => null,
            'customer' => [
                'id' => (string) ($c['id'] ?? ''),
                'email' => $c['email'] ?? null,
                'tags' => self::tags($c['tags'] ?? ''),
                'orders_count' => isset($c['orders_count']) ? (int) $c['orders_count'] : null,
                'total_spent' => isset($c['total_spent']) ? (float) $c['total_spent'] : null,
            ],
        ];
    }

    public static function fromEvent(string $event, ?string $label, ?string $experience): array
    {
        return ['order' => null, 'customer' => null, 'event' => ['name' => $event, 'label' => $label, 'experience' => $experience]];
    }

    /** A sample order for "Test workflow". */
    public static function sample(): array
    {
        return self::fromOrder([
            'id' => 1001, 'name' => '#1001 (test)', 'current_total_price' => '120.00', 'currency' => 'USD', 'created_at' => now()->toIso8601String(),
            'line_items' => [['product_id' => 1, 'variant_id' => 11, 'sku' => 'SAMPLE-1', 'title' => 'Sample product', 'quantity' => 2]],
            'shipping_lines' => [['title' => 'Standard']], 'payment_gateway_names' => ['shopify_payments'], 'fulfillment_status' => null,
            'shipping_address' => ['country_code' => 'US', 'province_code' => 'NY'], 'tags' => '',
            'customer' => ['id' => 501, 'tags' => '', 'orders_count' => 1, 'total_spent' => '120.00'],
        ]);
    }

    /** "Order #1052", for run lists. */
    public static function subject(array $context): string
    {
        return match (true) {
            ! empty($context['order']['name']) => 'Order '.$context['order']['name'],
            ! empty($context['order']['id']) => 'Order '.$context['order']['id'],
            ! empty($context['customer']['id']) => 'Customer '.$context['customer']['id'],
            ! empty($context['event']['name']) => 'Event: '.str_replace('_', ' ', $context['event']['name']),
            default => '—',
        };
    }

    /** Values for {{placeholders}} in text. */
    public static function placeholders(array $context, array $results, string $shop): array
    {
        $order = $context['order'] ?? [];

        return [
            'order_name' => $order['name'] ?? '',
            'order_total' => isset($order['total']) ? number_format((float) $order['total'], 2).' '.($order['currency'] ?? '') : '',
            'product_titles' => implode(', ', array_slice($order['titles'] ?? [], 0, 3)),
            'shop_name' => $shop,
            'discount_code' => collect($results)->pluck('discount_code')->filter()->last() ?? '',
            'event_name' => str_replace('_', ' ', (string) ($context['event']['name'] ?? '')),
        ];
    }

    public static function fill(string $text, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $text);
    }

    public static function daysSince(?string $iso): ?float
    {
        return $iso ? round(Carbon::parse($iso)->diffInMinutes(now(), true) / 1440, 2) : null;
    }

    /** @return list<string> */
    private static function tags(mixed $tags): array
    {
        $list = is_array($tags) ? $tags : explode(',', (string) $tags);

        return array_values(array_filter(array_map(fn ($t) => trim((string) $t), $list), fn ($t) => $t !== ''));
    }
}
