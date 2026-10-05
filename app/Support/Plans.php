<?php

namespace App\Support;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Plans come from the database (edited in the Internal Admin) and are loaded into
 * config('shopify.billing.plans') on boot, so the rest of the app reads them as before.
 */
class Plans
{
    private const KEY = 'billing-plans:v1';

    /** Loads the stored plans over the config defaults; a no-op until the table exists. */
    public static function boot(): void
    {
        try {
            $plans = Cache::get(self::KEY);
            if ($plans === null && Schema::hasTable('plans')) {
                $plans = Plan::orderBy('position')->orderBy('id')->get()->mapWithKeys(fn (Plan $p) => [$p->key => [
                    'name' => $p->name, 'shopify_name' => $p->shopify_name, 'price' => $p->price, 'trial_days' => $p->trial_days,
                    'sales_limit' => $p->sales_limit, 'includes' => array_values($p->modules ?? []), 'limits' => $p->limits ?? [],
                    'features' => $p->features ?? [], 'description' => $p->description, 'public' => $p->is_public, 'active' => $p->is_active,
                ]])->all() ?: null;
                if ($plans) {
                    Cache::forever(self::KEY, $plans);
                }
            }
        } catch (Throwable) {
            return; // No database yet (build steps, first deploy).
        }

        if ($plans) {
            config(['shopify.billing.plans' => $plans]);
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
        self::boot();
    }

    /** Plans shown to merchants (pricing pages and the in-app plan list). */
    public static function public(): array
    {
        return array_filter((array) config('shopify.billing.plans'), fn ($p) => ($p['public'] ?? true) && ($p['active'] ?? true));
    }
}
