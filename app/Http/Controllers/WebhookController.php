<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\WebhookReceipt;
use App\Services\Shopify\Billing;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __invoke(Request $request, Billing $billing): Response
    {
        $topic = (string) $request->header('X-Shopify-Topic');
        $shop = (string) $request->header('X-Shopify-Shop-Domain');
        $webhookId = (string) $request->header('X-Shopify-Webhook-Id');

        try {
            $receipt = WebhookReceipt::create(['webhook_id' => $webhookId, 'shop_domain' => $shop, 'topic' => $topic]);
        } catch (UniqueConstraintViolationException) {
            return response()->noContent(); // Already processed; Shopify retries are idempotent.
        }

        $store = Store::where('shop_domain', $shop)->first();
        $payload = $request->json()->all();

        match ($topic) {
            'app/uninstalled' => $this->uninstalled($store),
            'app/scopes_update' => $store?->forceFill(['scopes' => implode(',', $payload['current'] ?? [])])->save(),
            'app_subscriptions/update' => $store && $billing->applyWebhook($store, $payload),
            'shop/redact' => $this->redactShop($store),
            'customers/redact', 'customers/data_request' => AuditLog::record("compliance.{$topic}", $store, [
                'customer_id' => $payload['customer']['id'] ?? null,
                'note' => 'No customer PII stored yet.',
            ]),
            default => null,
        };

        $receipt->forceFill(['processed_at' => now()])->save();

        return response()->noContent();
    }

    private function uninstalled(?Store $store): void
    {
        if ($store === null) {
            return;
        }

        $store->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'access_token_expires_at' => null,
            'plan' => null,
            'uninstalled_at' => now(),
        ])->save();

        $store->subscriptions()->where('status', 'ACTIVE')->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);

        AuditLog::record('store.uninstalled', $store);
    }

    private function redactShop(?Store $store): void
    {
        if ($store === null) {
            return;
        }

        AuditLog::record('compliance.shop_redact', null, ['shop_domain' => $store->shop_domain]);
        $store->delete();
    }
}
