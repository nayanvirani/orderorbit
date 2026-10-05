<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Recommended widget limits and plan feature lines ("widget" wording, Shopify-length lines), from
 * the config defaults. Prices, names and modules set in the admin are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = (require config_path('shopify.php'))['billing']['plans'];
        foreach ($defaults as $key => $plan) {
            DB::table('plans')->where('key', $key)->update([
                'limits' => json_encode($plan['limits']), 'features' => json_encode($plan['features']), 'updated_at' => now(),
            ]);
        }
        Cache::forget('billing-plans:v2');
    }

    public function down(): void {}
};
