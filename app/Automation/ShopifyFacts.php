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
    /** Customer fields that need no protected-data approval beyond reading customers. */
    private const CUSTOMER = 'id tags numberOfOrders amountSpent { amount }';

    private const ORDER_BASE = 'id name createdAt tags currencyCode displayFulfillmentStatus paymentGatewayNames
        currentTotalPriceSet { shopMoney { amount } } shippingLines(first: 1) { nodes { title } }
        lineItems(first: 50) { nodes { title variantTitle quantity sku product { id } variant { id } } }';

    private const ORDER_FULL = self::ORDER_BASE.' shippingAddress { countryCodeV2 provinceCode } customer { id email firstName lastName tags numberOfOrders amountSpent { amount } }';

    private const ORDER_SAFE = self::ORDER_BASE.' customer { '.self::CUSTOMER.' }';

    private const ORDER_MINIMAL = self::ORDER_BASE;

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
            // Shown with the condition that needed it, and in the server log.
            \Illuminate\Support\Facades\Log::channel(app()->runningUnitTests() ? 'null' : 'stderr')->warning('automation customer lookup failed', ['store' => $store->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            $context['customer']['lookup_error'] = mb_substr($e->getMessage(), 0, 200);

            return $context;
        }
        if (! $c) {
            $context['customer']['lookup_error'] = 'the customer wasn\'t found';

            return $context;
        }
        $context['customer'] = array_merge($customer, [
            'total_spent' => $customer['total_spent'] ?? (isset($c['amountSpent']['amount']) ? (float) $c['amountSpent']['amount'] : null),
            'orders_count' => $customer['orders_count'] ?? (isset($c['numberOfOrders']) ? (int) $c['numberOfOrders'] : null),
            'tags' => $customer['tags'] ?: array_values(array_filter(array_map('trim', (array) ($c['tags'] ?? [])))),
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
     * there's no such order. Throws a plain-words RuntimeException when Shopify can't be read.
     *
     * Names, emails and addresses are protected customer data: when the app isn't approved for
     * them Shopify rejects the whole query, so it's asked again without them.
     */
    public function order(Store $store, ?string $name = null): ?array
    {
        $name = ltrim(trim((string) $name), '#');
        $last = null;
        foreach ([self::ORDER_FULL, self::ORDER_SAFE, self::ORDER_MINIMAL] as $fields) {
            try {
                $o = null;
                // Order search matches "name:#1011"; some stores' names have no "#", so both are tried.
                foreach ($name !== '' ? ['name:#'.$name, 'name:'.$name] : [null] as $q) {
                    $o = $this->api->graphql($store, 'query ($q: String) { orders(first: 1, sortKey: CREATED_AT, reverse: true, query: $q) { nodes { '.$fields.' } } }', ['q' => $q])['orders']['nodes'][0] ?? null;
                    if ($o && ($name === '' || ltrim((string) $o['name'], '#') === $name)) {
                        break;
                    }
                    $o = null;
                }
                $last = null;
                break;
            } catch (Throwable $e) {
                $last = $e;
            }
        }
        if ($last) {
            report($last);
            throw new \RuntimeException('Growvia couldn\'t read orders from Shopify just now. Try again in a minute.');
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
            'customer' => $c ? ['id' => $id($c['id']), 'email' => $c['email'] ?? null, 'first_name' => $c['firstName'] ?? null, 'last_name' => $c['lastName'] ?? null,
                'tags' => implode(', ', (array) $c['tags']), 'orders_count' => $c['numberOfOrders'], 'total_spent' => $c['amountSpent']['amount'] ?? null] : null,
        ]);
    }
}
