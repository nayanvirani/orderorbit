<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/**
 * Renewal orders from a subscription app have no checkout, so the pixel never sees them. They are
 * counted from the orders/create webhook and credited to the Growvia bundle or offer they came from.
 */
class SubscriptionRenewalTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(fn () => Http::response(['data' => []]));
    }

    private function webhook(array $payload, string $id)
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/shopify', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $this->shop,
            'HTTP_X_SHOPIFY_WEBHOOK_ID' => $id,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, 'test-secret', true)),
        ], $body);
    }

    private function order(int $id, array $extra = []): array
    {
        return $extra + [
            'id' => $id, 'created_at' => now()->toIso8601String(), 'currency' => 'USD', 'total_price' => '54.00', 'checkout_token' => null,
            'source_name' => 'subscription_contract', 'customer' => ['id' => 77], 'shipping_address' => ['country_code' => 'CA'],
            'line_items' => [
                ['product_id' => 1, 'quantity' => 2, 'price' => '27.00', 'title' => 'Glow Serum', 'discount_allocations' => [['amount' => '5.40']],
                    'properties' => [['name' => '_oo_bundle', 'value' => 'exp-bundle|2'], ['name' => 'Delivery every', 'value' => '30 days']]],
                ['product_id' => 2, 'quantity' => 1, 'price' => '5.40', 'title' => 'Shipping protection', 'properties' => []],
            ],
        ];
    }

    public function test_a_renewal_counts_as_revenue_once_and_credits_the_bundle(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);

        $this->webhook($this->order(5001), 'r-1')->assertNoContent();
        $this->webhook($this->order(5001), 'r-2')->assertNoContent(); // the same order sent again

        $order = AnalyticsEvent::where('store_id', $store->id)->where('event', 'order')->sole();
        $this->assertSame('subscription_renewal', $order->name);
        $this->assertSame(54.0, $order->value);
        $this->assertSame('subscription', $order->source);
        $this->assertSame('CA', $order->country);

        $credit = AnalyticsEvent::where('store_id', $store->id)->where('event', 'attributed')->sole();
        $this->assertSame('exp-bundle', $credit->experience_handle);
        $this->assertSame(48.6, $credit->value); // 2 × 27.00 − 5.40 discount
    }

    public function test_orders_placed_through_checkout_are_left_to_the_pixel(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);

        $this->webhook($this->order(5002, ['checkout_token' => 'abc123', 'source_name' => 'web']), 'r-3')->assertNoContent();
        $this->webhook($this->order(5003, ['source_name' => 'shopify_draft_order', 'line_items' => [['product_id' => 1, 'quantity' => 1, 'price' => '10.00', 'properties' => []]]]), 'r-4')->assertNoContent();

        $this->assertSame(0, AnalyticsEvent::where('store_id', $store->id)->whereIn('event', ['order', 'attributed'])->count());
    }
}
