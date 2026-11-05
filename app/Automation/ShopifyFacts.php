<?php

namespace App\Automation;

use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Throwable;

/**
 * Facts a webhook doesn't carry, read from Shopify when a workflow needs them: the customer's
 * lifetime spend, order count and tags (newer order webhooks leave them out), and the store's
 * latest order for "Test with an order".
 */
class ShopifyFacts
{
    private const CUSTOMER = 'id email firstName lastName tags numberOfOrders amountSpent { amount }';

    public function __construct(private readonly AdminApi $api) {}

    /** Fills in the customer's total spent, order count and tags when the payload left them out. */
    public function withCustomer(Store $store, array $context): array
    {
        $customer = $context['customer'] ?? null;
        if (! $customer || empty($customer['id']) || ($customer['total_spent'] !== null && $customer['orders_count'] !== null)) {
            return $context;
        }
        try {
            $c = $this->api->graphql($store, 'query ($id: ID!) { customer(id: $id) { '.self::CUSTOMER.' } }', ['id' => 'gid://shopify/Customer/'.$customer['id']])['customer'] ?? null;
        } catch (Throwable $e) {
            report($e);

            return $context;
        }
        if (! $c) {
            return $context;
        }
        $context['customer'] = array_merge($customer, [
            'total_spent' => $customer['total_spent'] ?? (isset($c['amountSpent']['amount']) ? (float) $c['amountSpent']['amount'] : null),
            'orders_count' => $customer['orders_count'] ?? (isset($c['numberOfOrders']) ? (int) $c['numberOfOrders'] : null),
            'tags' => $customer['tags'] ?: array_values(array_filter(array_map('trim', (array) ($c['tags'] ?? [])))),
            'email' => $customer['email'] ?? $c['email'] ?? null,
            'name' => $customer['name'] ?? (trim(($c['firstName'] ?? '').' '.($c['lastName'] ?? '')) ?: null),
        ]);

        return $context;
    }

    /** The store's most recent order as a run context, or null when there is none (or it can't be read). */
    public function latestOrder(Store $store): ?array
    {
        return $this->order($store);
    }

    /**
     * An order as a run context: the one named (#1001 or 1001), or the most recent. Null when
     * there's no such order or it can't be read.
     */
    public function order(Store $store, ?string $name = null): ?array
    {
        $name = $name !== null ? trim($name) : '';
        $query = $name !== '' ? 'name:'.(str_starts_with($name, '#') ? $name : '#'.$name) : null;
        try {
            $o = $this->api->graphql($store, 'query ($q: String) { orders(first: 1, sortKey: CREATED_AT, reverse: true, query: $q) { nodes {
                id name createdAt tags currencyCode displayFulfillmentStatus paymentGatewayNames
                currentTotalPriceSet { shopMoney { amount } }
                shippingAddress { countryCodeV2 provinceCode }
                shippingLines(first: 1) { nodes { title } }
                lineItems(first: 50) { nodes { title variantTitle quantity sku product { id } variant { id } } }
                customer { '.self::CUSTOMER.' } } } }', ['q' => $query])['orders']['nodes'][0] ?? null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        if (! $o) {
            return null;
        }
        $id = fn (?string $gid) => $gid ? (string) last(explode('/', $gid)) : null;
        $c = $o['customer'] ?? null;

        // In the shape of an order webhook, so it goes through the same Context::fromOrder.
        return Context::fromOrder([
            'id' => $id($o['id']), 'name' => $o['name'], 'created_at' => $o['createdAt'], 'currency' => $o['currencyCode'],
            'current_total_price' => $o['currentTotalPriceSet']['shopMoney']['amount'] ?? 0, 'tags' => implode(', ', (array) ($o['tags'] ?? [])),
            'fulfillment_status' => match ($o['displayFulfillmentStatus'] ?? null) { 'FULFILLED' => 'fulfilled', 'PARTIALLY_FULFILLED' => 'partial', default => null },
            'payment_gateway_names' => $o['paymentGatewayNames'] ?? [],
            'shipping_address' => ['country_code' => $o['shippingAddress']['countryCodeV2'] ?? null, 'province_code' => $o['shippingAddress']['provinceCode'] ?? null],
            'shipping_lines' => array_map(fn ($s) => ['title' => $s['title']], $o['shippingLines']['nodes'] ?? []),
            'line_items' => array_map(fn ($l) => ['title' => $l['title'], 'variant_title' => $l['variantTitle'], 'quantity' => $l['quantity'], 'sku' => $l['sku'],
                'product_id' => $id($l['product']['id'] ?? null), 'variant_id' => $id($l['variant']['id'] ?? null)], $o['lineItems']['nodes'] ?? []),
            'customer' => $c ? ['id' => $id($c['id']), 'email' => $c['email'], 'first_name' => $c['firstName'], 'last_name' => $c['lastName'],
                'tags' => implode(', ', (array) $c['tags']), 'orders_count' => $c['numberOfOrders'], 'total_spent' => $c['amountSpent']['amount'] ?? null] : null,
        ]);
    }
}
