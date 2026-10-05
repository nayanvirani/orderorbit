<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\WebhookReceipt;
use App\Services\SalesPop\RecentOrders;
use App\Services\Shopify\Billing;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __invoke(Request $request, Billing $billing, RecentOrders $orders): Response
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
            'app/scopes_update' => $this->scopesUpdated($store, $payload, $orders),
            'app_subscriptions/update' => $store && $billing->applyWebhook($store, $payload),
            // Sales pop: products from real orders (no customer details are kept).
            'orders/create' => $store && $store->isInstalled() && $this->orderChanged($store, $payload, $orders, true),
            'orders/updated', 'orders/cancelled' => $store && $store->isInstalled() && $this->orderChanged($store, $payload, $orders, false),
            // Workflow triggers only.
            'orders/paid', 'orders/fulfilled', 'refunds/create', 'customers/create', 'customers/update' => $store && $store->isInstalled() && $this->automation($store, $topic, $payload),
            'shop/redact' => $this->redactShop($store),
            'customers/redact' => $this->redactCustomer($store, $payload),
            'customers/data_request' => AuditLog::record("compliance.{$topic}", $store, [
                'customer_id' => $payload['customer']['id'] ?? null,
                'note' => 'Automation may hold the customer id, tags and prepared emails for this customer; analytics may hold their customer id with their storefront events (no names, emails or addresses).',
            ]),
            default => null,
        };

        $receipt->forceFill(['processed_at' => now()])->save();

        return response()->noContent();
    }

    /** A new or changed order: a new order's products feed Sales pop, and either can start workflows. */
    private function orderChanged(Store $store, array $payload, RecentOrders $orders, bool $created): void
    {
        if ($created) {
            $orders->fromWebhook($store, $payload);
        }
        $this->automation($store, $created ? 'orders/create' : (string) request()->header('X-Shopify-Topic'), $payload);
    }

    /** Starts matching workflows after the response is sent. */
    private function automation(Store $store, string $topic, array $payload): void
    {
        defer(fn () => app(\App\Automation\Triggers::class)->webhook($store, $topic, $payload));
    }

    private function redactCustomer(?Store $store, array $payload): void
    {
        $id = (string) ($payload['customer']['id'] ?? '');
        if ($store && $id !== '') {
            app(\App\Automation\Triggers::class)->forget($store, $id);
            \App\Services\Analytics\Journeys::forget($store, $id);
        }
        AuditLog::record('compliance.customers/redact', $store, ['customer_id' => $id ?: null]);
    }

    private function scopesUpdated(?Store $store, array $payload, RecentOrders $orders): void
    {
        if ($store === null) {
            return;
        }
        $store->forceFill(['scopes' => implode(',', $payload['current'] ?? [])])->save();

        // Orders just became readable: give a live Sales pop its recent orders.
        if ($store->hasScope('read_orders') && $store->experiences()->where('type', 'sales-pop')->where('status', 'published')->exists()
            && ! \App\Models\RecentPurchase::where('store_id', $store->id)->exists()) {
            try {
                $orders->import($store);
            } catch (\Throwable $e) {
                report($e);
            }
        }
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
            // Shopify removes the app's pixel, cart transform and discounts on uninstall.
            'web_pixel_id' => null,
            'cart_transform_id' => null,
        ])->save();

        // Offers stop with the app; merchants republish after reinstalling.
        $store->experiences()->where('status', 'published')->update(['status' => 'paused']);
        $store->experiences()->whereNotNull('shopify_discount_id')->update(['shopify_discount_id' => null]);

        // Sales pop data is only kept while the app is installed.
        \App\Models\RecentPurchase::where('store_id', $store->id)->delete();

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
