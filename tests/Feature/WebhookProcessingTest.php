<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class WebhookProcessingTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
    }

    private function webhook(string $topic, array $payload, string $id = 'wh-1')
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/shopify', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_TOPIC' => $topic,
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $this->shop,
            'HTTP_X_SHOPIFY_WEBHOOK_ID' => $id,
            'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, 'test-secret', true)),
        ], $body);
    }

    public function test_uninstall_clears_tokens_once(): void
    {
        $store = $this->installedStore(['plan' => 'growth']);

        $this->webhook('app/uninstalled', ['id' => 1])->assertNoContent();
        $this->webhook('app/uninstalled', ['id' => 1])->assertNoContent();

        $store->refresh();
        $this->assertNull($store->access_token);
        $this->assertNull($store->plan);
        $this->assertNotNull($store->uninstalled_at);
        $this->assertSame(1, AuditLog::where('action', 'store.uninstalled')->count());
        $this->assertSame('system', AuditLog::where('action', 'store.uninstalled')->value('actor_type'));
    }

    public function test_subscription_webhook_activates_plan(): void
    {
        $store = $this->installedStore();

        $this->webhook('app_subscriptions/update', ['app_subscription' => [
            'admin_graphql_api_id' => 'gid://shopify/AppSubscription/1', 'name' => 'OrderOrbit Growth', 'status' => 'ACTIVE',
        ]])->assertNoContent();

        $this->assertSame('growth', $store->fresh()->plan);
    }
}
