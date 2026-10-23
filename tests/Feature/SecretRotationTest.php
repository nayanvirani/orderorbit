<?php

namespace Tests\Feature;

use App\Services\Shopify\SessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** Rotating the Shopify client secret: the old one keeps working until it's removed. */
class SecretRotationTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private function webhook(string $secret)
    {
        $body = json_encode(['id' => 1]);

        return $this->call('POST', '/webhooks/shopify', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SHOPIFY_TOPIC' => 'customers/data_request', 'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $this->shop,
            'HTTP_X_SHOPIFY_WEBHOOK_ID' => 'w-'.$secret, 'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, $secret, true)),
        ], $body);
    }

    private function token(string $secret): string
    {
        $b = fn ($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
        $input = $b(['alg' => 'HS256', 'typ' => 'JWT']).'.'.$b(['iss' => "https://{$this->shop}/admin", 'dest' => "https://{$this->shop}", 'aud' => config('shopify.api_key'), 'sub' => '1', 'exp' => time() + 60, 'nbf' => time() - 5, 'iat' => time(), 'jti' => 'j']);

        return $input.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $input, $secret, true)), '+/', '-_'), '=');
    }

    public function test_old_and_new_secrets_both_work_during_the_switch(): void
    {
        $this->setUpShopify();
        $this->installedStore();
        config(['shopify.api_secret' => 'new-secret', 'shopify.api_secret_previous' => 'test-secret']);
        $this->app->forgetInstance(SessionToken::class);

        $this->webhook('new-secret')->assertNoContent();
        $this->webhook('test-secret')->assertNoContent();
        $this->webhook('someone-else')->assertStatus(401);

        $tokens = app(SessionToken::class);
        $this->assertSame('1', $tokens->decode($this->token('new-secret'))['sub']);
        $this->assertSame('1', $tokens->decode($this->token('test-secret'))['sub']);

        // After the old secret is removed it stops working.
        config(['shopify.api_secret_previous' => null]);
        $this->app->forgetInstance(SessionToken::class);
        $this->webhook('test-secret')->assertStatus(401);
        $this->expectException(\App\Exceptions\InvalidSessionToken::class);
        app(SessionToken::class)->decode($this->token('test-secret'));
    }
}
