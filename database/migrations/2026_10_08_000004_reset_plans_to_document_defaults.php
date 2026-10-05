<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sets the plans to "MVP Pricing & Plan Accessibility (2026)". Reads the defaults from the config
 * file itself: at runtime config('shopify.billing.plans') already holds the stored plans.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = (require config_path('shopify.php'))['billing']['plans'];
        $position = 0;
        foreach ($defaults as $key => $plan) {
            DB::table('plans')->updateOrInsert(['key' => $key], [
                'name' => $plan['name'], 'shopify_name' => $plan['shopify_name'], 'price' => $plan['price'],
                'modules' => json_encode($plan['includes']), 'limits' => json_encode($plan['limits']), 'features' => json_encode($plan['features']),
                'description' => $plan['description'] ?? null, 'support_label' => $plan['support'] ?? null, 'badge' => $plan['badge'] ?? null,
                'position' => $position++, 'is_public' => true, 'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
        Cache::forget('billing-plans:v1');
        Cache::forget('billing-plans:v2');
    }

    public function down(): void {}
};
