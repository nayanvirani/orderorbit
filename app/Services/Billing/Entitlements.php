<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Store;
use App\Services\Experiences\StorefrontPublisher;
use Throwable;

/**
 * Applies what a store may use to everything that runs outside the app: the storefront, checkout
 * and customer-account blocks, Shopify discounts and cart transform, and live counts over a limit.
 * Runs whenever the plan, the plan's features or limits, or the store's own overrides change,
 * and on a schedule (orderorbit:apply-entitlements) for changes nothing else announces, such as a
 * complimentary plan running out.
 */
class Entitlements
{
    public function __construct(private readonly PlanLimits $limits, private readonly StorefrontPublisher $publisher) {}

    /** @return bool whether anything was applied */
    public function apply(Store $store, bool $force = false): bool
    {
        if (! $store->isInstalled()) {
            return false;
        }
        $fingerprint = $store->entitlementsFingerprint();
        if (! $force && $fingerprint === $store->entitlements_hash) {
            return false;
        }

        $paused = $this->limits->apply($store, sync: false);
        // A store with nothing published and nothing in Shopify has nothing to update.
        $hasOutput = $store->cart_transform_id
            || $store->experiences()->where(fn ($q) => $q->where('status', 'published')->orWhereNotNull('shopify_discount_id'))->exists();
        if ($hasOutput || $paused > 0) {
            try {
                // Features the store lost stop at once; ones it gained go live again.
                $this->publisher->sync($store);
            } catch (Throwable $e) {
                report($e);

                return false; // Not recorded: the next run tries again.
            }
        }

        $store->forceFill(['entitlements_hash' => $fingerprint])->save();
        AuditLog::record('store.entitlements_applied', $store, ['plan' => $store->effectivePlan(), 'paused' => $paused]);

        return true;
    }
}
