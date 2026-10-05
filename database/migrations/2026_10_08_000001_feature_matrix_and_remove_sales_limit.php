<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "MVP Pricing & Plan Accessibility (2026)": plans are defined by features and usage limits
 * (App\Support\Modules), not by the store's sales. Removes the store-sales limit entirely and
 * resets the plans to the document's model (the app is still in development).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('sales_limit');
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->string('badge', 40)->nullable();
            $table->string('support_label', 80)->nullable();
        });

        $position = 0;
        foreach ((array) config('shopify.billing.plans') as $key => $plan) {
            DB::table('plans')->updateOrInsert(['key' => $key], [
                'name' => $plan['name'], 'shopify_name' => $plan['shopify_name'], 'price' => $plan['price'],
                'modules' => json_encode($plan['includes']), 'limits' => json_encode($plan['limits']), 'features' => json_encode($plan['features']),
                'description' => $plan['description'] ?? null, 'support_label' => $plan['support'] ?? null, 'badge' => $plan['badge'] ?? null,
                'position' => $position++, 'is_public' => true, 'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        // Store overrides: the sales limit is gone; rename modules that were split.
        foreach (DB::table('stores')->whereNotNull('entitlements')->get(['id', 'entitlements']) as $row) {
            $e = (array) json_decode($row->entitlements, true);
            unset($e['sales_limit']);
            foreach (['modules_on', 'modules_off'] as $list) {
                if (in_array('checkout', $e[$list] ?? [], true)) {
                    $e[$list] = array_values(array_unique([...$e[$list], 'thank_you', 'post_purchase']));
                }
                if (in_array('advanced_analytics', $e[$list] ?? [], true)) {
                    $e[$list] = array_values(array_unique([...$e[$list], 'funnels_attribution', 'customer_journeys']));
                }
            }
            DB::table('stores')->where('id', $row->id)->update(['entitlements' => $e === [] ? null : json_encode($e)]);
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['cycle_sales_usd', 'sales_checked_at', 'over_limit_since', 'offers_suspended_at', 'cycle_anchor_at', 'cycle_started_at', 'over_limit_plan']);
        });
        Schema::table('stores', function (Blueprint $table) {
            // Fingerprint of the plan, features and limits last applied to the storefront and checkout.
            $table->string('entitlements_hash', 64)->nullable();
        });
        Schema::dropIfExists('sales_cycles');
        Schema::dropIfExists('store_orders');

        DB::table('platform_settings')->whereIn('key', ['grace_days', 'count_test_orders_for'])->delete();
        Cache::forget('billing-plans:v1');
        Cache::forget('platform-settings:v1');
    }

    public function down(): void
    {
        // One-way: the sales limit and its data are removed for good.
    }
};
