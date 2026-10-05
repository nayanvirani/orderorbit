<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use App\Services\Billing\Entitlements;
use App\Services\Experiences\PlacementDetector;
use App\Services\Shopify\Billing;
use App\Services\Shopify\StoreSync;
use App\Services\Usage;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Per-store access set by the OrderOrbit team: a complimentary plan, features switched on or off
 * on top of the plan and limit overrides; plus one-click support actions.
 */
class StoreAccessController extends Controller
{
    public function save(Request $request, int $store): RedirectResponse
    {
        $store = Store::findOrFail($store);
        $plans = array_keys((array) config('shopify.billing.plans'));
        $data = $request->validate([
            'plan' => ['nullable', 'in:'.implode(',', $plans)],
            'plan_until' => ['nullable', 'date', 'after:yesterday'],
            'modules' => ['array'], 'modules.*' => ['in:default,on,off'],
            'limits' => ['array'], 'limits.*.mode' => ['in:default,value,unlimited'], 'limits.*.value' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $modules = collect($data['modules'] ?? [])->only(array_keys(Modules::ALL));
        $limits = [];
        foreach (array_keys(Usage::METERS) as $meter) {
            $row = $data['limits'][$meter] ?? ['mode' => 'default'];
            if (($row['mode'] ?? 'default') === 'unlimited') {
                $limits[$meter] = null;
            } elseif (($row['mode'] ?? 'default') === 'value') {
                $limits[$meter] = (int) ($row['value'] ?? 0);
            }
        }
        $entitlements = array_filter([
            'plan' => $data['plan'] ?? null,
            'plan_until' => ! empty($data['plan']) && ! empty($data['plan_until']) ? $data['plan_until'] : null,
            'modules_on' => $modules->filter(fn ($v) => $v === 'on')->keys()->values()->all(),
            'modules_off' => $modules->filter(fn ($v) => $v === 'off')->keys()->values()->all(),
            'limits' => $limits,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
        ], fn ($v) => $v !== null && $v !== []);

        $before = $store->entitlements;
        $store->forceFill(['entitlements' => $entitlements ?: null])->save();
        AuditLog::record('admin.store_access_changed', $store, ['before' => $before, 'after' => $entitlements, 'by' => $request->user()->email]);

        // Features and limits reach the storefront, checkout and Shopify discounts at once.
        if (! app(Entitlements::class)->apply($store->fresh(), force: true) && $store->isInstalled()) {
            return back()->with('status', 'Access saved. The storefront will update within a few minutes (Shopify didn\'t answer).');
        }

        return back()->with('status', 'Access saved and the storefront updated.');
    }

    public function action(Request $request, int $store, string $action): RedirectResponse
    {
        $store = Store::findOrFail($store);

        try {
            $message = match ($action) {
                'sync-billing' => (app(Billing::class)->sync($store) ? 'Subscription synced from Shopify.' : 'Shopify reports no active subscription.'),
                'recheck-placement' => (app(PlacementDetector::class)->refresh($store) ? 'Theme placement re-checked.' : ''),
                'apply-access' => (app(Entitlements::class)->apply($store, force: true) ? 'Plan features and limits re-applied to the storefront and checkout.' : 'Shopify didn\'t answer; it will be retried within a few minutes.'),
                'refresh-store' => (app(StoreSync::class)->sync($store) ? 'Store details and capabilities refreshed.' : ''),
            };
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['action' => 'That didn\'t work: '.$e->getMessage()]);
        }
        AuditLog::record('admin.store_'.str_replace('-', '_', $action), $store, ['by' => $request->user()->email]);

        return back()->with('status', $message ?: 'Done.');
    }
}
