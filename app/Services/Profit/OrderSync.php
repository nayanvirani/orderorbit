<?php

namespace App\Services\Profit;

use App\Models\Profit\ProfitOrder;
use App\Models\Store;
use App\Services\Shopify\AdminApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps Profit's copy of orders current: from order webhooks (REST JSON) and a backfill through
 * the Admin GraphQL API. Both are turned into the same shape; amounts are in the shop's currency.
 * Today's write_orders access covers the last 60 days; read_all_orders (once Shopify approves it)
 * opens older history.
 */
class OrderSync
{
    public function __construct(private readonly AdminApi $api) {}

    /** An order webhook (orders/create, orders/updated, orders/cancelled, refunds via orders/updated). */
    public function fromWebhook(Store $store, array $o): ?ProfitOrder
    {
        if (empty($o['id'])) {
            return null;
        }
        $money = fn ($set, $plain) => (float) ($set['shop_money']['amount'] ?? $plain ?? 0);
        $refundedQty = [];
        $refundedAmount = [];
        $shippingRefund = 0.0;
        foreach ((array) ($o['refunds'] ?? []) as $refund) {
            foreach ((array) ($refund['refund_line_items'] ?? []) as $r) {
                $id = (string) ($r['line_item_id'] ?? '');
                $refundedQty[$id] = ($refundedQty[$id] ?? 0) + (int) ($r['quantity'] ?? 0);
                $refundedAmount[$id] = ($refundedAmount[$id] ?? 0) + $money($r['subtotal_set'] ?? null, $r['subtotal'] ?? 0);
            }
            foreach ((array) ($refund['order_adjustments'] ?? []) as $adj) {
                if (($adj['kind'] ?? '') === 'shipping_refund') {
                    $shippingRefund += abs($money($adj['amount_set'] ?? null, $adj['amount'] ?? 0));
                }
            }
            foreach ((array) ($refund['refund_shipping_lines'] ?? []) as $line) {
                $shippingRefund += abs($money($line['subtotal_amount_set'] ?? null, $line['subtotal_amount'] ?? 0));
            }
        }

        $lines = array_map(fn ($l) => [
            'line_id' => (string) ($l['id'] ?? ''),
            'product_id' => $l['product_id'] ?? null, 'variant_id' => $l['variant_id'] ?? null,
            'title' => $l['title'] ?? null, 'variant_title' => $l['variant_title'] ?? null, 'sku' => $l['sku'] ?? null,
            'quantity' => (int) ($l['quantity'] ?? 0),
            'price' => $money($l['price_set'] ?? null, $l['price'] ?? 0),
            'discount' => array_sum(array_map(fn ($a) => $money($a['amount_set'] ?? null, $a['amount'] ?? 0), (array) ($l['discount_allocations'] ?? []))),
            'refunded_quantity' => $refundedQty[(string) ($l['id'] ?? '')] ?? 0,
            'refunded' => $refundedAmount[(string) ($l['id'] ?? '')] ?? 0,
        ], (array) ($o['line_items'] ?? []));

        return $this->save($store, [
            'order_id' => (int) $o['id'], 'name' => $o['name'] ?? null,
            'processed_at' => $o['processed_at'] ?? $o['created_at'] ?? now()->toIso8601String(),
            'currency' => $o['currency'] ?? null, 'channel' => $o['source_name'] ?? null,
            'country' => strtoupper((string) ($o['shipping_address']['country_code'] ?? $o['billing_address']['country_code'] ?? '')) ?: null,
            'customer_id' => $o['customer']['id'] ?? null,
            'returning' => (int) ($o['customer']['orders_count'] ?? $o['customer']['number_of_orders'] ?? 1) > 1,
            'gateway' => $o['payment_gateway_names'][0] ?? null,
            'shipping' => max(0, $money($o['total_shipping_price_set'] ?? null, 0) - $shippingRefund),
            'taxes' => $money($o['total_tax_set'] ?? null, $o['total_tax'] ?? 0),
            'total' => $money($o['total_price_set'] ?? null, $o['total_price'] ?? 0),
            'excluded' => ! empty($o['cancelled_at']) || ! empty($o['test']),
        ], $lines);
    }

    /** One order from the Admin GraphQL API (see ORDER_FIELDS). */
    public function fromGraphql(Store $store, array $o): ?ProfitOrder
    {
        $id = (int) Str::afterLast((string) ($o['id'] ?? ''), '/');
        if (! $id) {
            return null;
        }
        $num = fn ($set) => (float) ($set['shopMoney']['amount'] ?? 0);
        $gid = fn ($v) => $v ? (int) Str::afterLast((string) $v, '/') : null;
        $refundedQty = [];
        $refundedAmount = [];
        $shippingRefund = 0.0;
        foreach ((array) ($o['refunds'] ?? []) as $refund) {
            foreach ((array) ($refund['refundLineItems']['nodes'] ?? []) as $r) {
                $line = (string) $gid($r['lineItem']['id'] ?? null);
                $refundedQty[$line] = ($refundedQty[$line] ?? 0) + (int) ($r['quantity'] ?? 0);
                $refundedAmount[$line] = ($refundedAmount[$line] ?? 0) + $num($r['subtotalSet'] ?? null);
            }
            foreach ((array) ($refund['refundShippingLines']['nodes'] ?? []) as $s) {
                $shippingRefund += abs($num($s['subtotalAmountSet'] ?? null));
            }
        }
        $lines = array_map(function ($l) use ($num, $gid, $refundedQty, $refundedAmount) {
            $line = (string) $gid($l['id'] ?? null);

            return [
                'line_id' => $line, 'product_id' => $gid($l['product']['id'] ?? null), 'variant_id' => $gid($l['variant']['id'] ?? null),
                'title' => $l['title'] ?? null, 'variant_title' => $l['variantTitle'] ?? null, 'sku' => $l['sku'] ?? null,
                'quantity' => (int) ($l['quantity'] ?? 0), 'price' => $num($l['originalUnitPriceSet'] ?? null),
                'discount' => array_sum(array_map(fn ($a) => $num($a['allocatedAmountSet'] ?? null), (array) ($l['discountAllocations'] ?? []))),
                'refunded_quantity' => $refundedQty[$line] ?? 0, 'refunded' => $refundedAmount[$line] ?? 0,
            ];
        }, (array) ($o['lineItems']['nodes'] ?? []));

        return $this->save($store, [
            'order_id' => $id, 'name' => $o['name'] ?? null, 'processed_at' => $o['processedAt'] ?? now()->toIso8601String(),
            'currency' => $o['totalPriceSet']['shopMoney']['currencyCode'] ?? null, 'channel' => $o['sourceName'] ?? null,
            'country' => $o['shippingAddress']['countryCodeV2'] ?? $o['billingAddress']['countryCodeV2'] ?? null,
            'customer_id' => $gid($o['customer']['id'] ?? null), 'returning' => (int) ($o['customer']['numberOfOrders'] ?? 1) > 1,
            'gateway' => $o['paymentGatewayNames'][0] ?? null,
            'shipping' => max(0, $num($o['totalShippingPriceSet'] ?? null) - $shippingRefund),
            'taxes' => $num($o['totalTaxSet'] ?? null), 'total' => $num($o['totalPriceSet'] ?? null),
            'excluded' => ! empty($o['cancelledAt']) || ! empty($o['test']),
        ], $lines);
    }

    public const ORDER_FIELDS = <<<'GQL'
        id name processedAt cancelledAt test sourceName paymentGatewayNames
        customer { id numberOfOrders }
        shippingAddress { countryCodeV2 } billingAddress { countryCodeV2 }
        totalPriceSet { shopMoney { amount currencyCode } } totalShippingPriceSet { shopMoney { amount } } totalTaxSet { shopMoney { amount } }
        refunds { refundLineItems(first: 100) { nodes { quantity lineItem { id } subtotalSet { shopMoney { amount } } } }
                  refundShippingLines(first: 10) { nodes { subtotalAmountSet { shopMoney { amount } } } } }
        lineItems(first: 100) { nodes { id title variantTitle sku quantity product { id } variant { id }
            originalUnitPriceSet { shopMoney { amount } } discountAllocations { allocatedAmountSet { shopMoney { amount } } } } }
        GQL;

    /**
     * Backfills orders processed since $since, a page at a time (re-runnable: orders are upserted).
     * Returns how many orders were saved.
     */
    public function backfill(Store $store, Carbon $since, int $maxPages = 400): int
    {
        $count = 0;
        $after = null;
        $query = 'processed_at:>='.$since->toDateString();
        for ($page = 0; $page < $maxPages; $page++) {
            $data = $this->api->graphql($store, 'query ($q: String!, $after: String) { orders(first: 50, after: $after, query: $q, sortKey: PROCESSED_AT) { pageInfo { hasNextPage endCursor } nodes { '.self::ORDER_FIELDS.' } } }',
                ['q' => $query, 'after' => $after]);
            foreach ((array) ($data['orders']['nodes'] ?? []) as $node) {
                if ($this->fromGraphql($store, $node)) {
                    $count++;
                }
            }
            if (! ($data['orders']['pageInfo']['hasNextPage'] ?? false)) {
                break;
            }
            $after = $data['orders']['pageInfo']['endCursor'];
        }

        return $count;
    }

    /** Upserts an order and its lines; returns it (the caller recalculates its day). */
    private function save(Store $store, array $order, array $lines): ProfitOrder
    {
        $processed = Carbon::parse($order['processed_at']);
        $day = $processed->copy()->setTimezone($store->timezone ?: 'UTC')->toDateString();
        $gross = round(array_sum(array_map(fn ($l) => $l['price'] * $l['quantity'], $lines)), 2);
        $discounts = round(array_sum(array_column($lines, 'discount')), 2);
        $refunded = round(array_sum(array_column($lines, 'refunded')), 2);

        return DB::transaction(function () use ($store, $order, $lines, $processed, $day, $gross, $discounts, $refunded) {
            $previous = ProfitOrder::where('store_id', $store->id)->where('order_id', $order['order_id'])->value('day');
            $saved = ProfitOrder::updateOrCreate(['store_id' => $store->id, 'order_id' => $order['order_id']], [
                'processed_at' => $processed, 'day' => $day, 'gross' => $gross, 'discounts' => $discounts, 'refunded' => $refunded,
            ] + array_diff_key($order, ['order_id' => 1, 'processed_at' => 1]));
            $saved->lines()->delete();
            foreach ($lines as $l) {
                $saved->lines()->create(['store_id' => $store->id] + array_diff_key($l, ['line_id' => 1]));
            }
            $saved->previousDay = $previous ? Carbon::parse($previous)->toDateString() : null;

            return $saved;
        });
    }
}
