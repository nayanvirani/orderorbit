<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans, modules and per-store access move into the database so the Internal Admin can edit
 * them: plan prices, limits and included modules; extra (or fewer) modules, a complimentary
 * plan and limit overrides for single stores; platform settings; admin team roles.
 */
return new class extends Migration
{
    /** Storefront modules every existing plan already had (before modules were gated). */
    private const STOREFRONT = ['bundles', 'progressive_gifts', 'cart_upsells', 'countdown', 'sticky_atc', 'preorder', 'sales_pop', 'trust'];

    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('shopify_name', 120);
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('trial_days')->default(0);
            $table->decimal('sales_limit', 14, 2)->nullable();
            $table->json('modules');
            $table->json('limits');
            $table->json('features');
            $table->string('description', 300)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $position = 0;
        foreach ((array) config('shopify.billing.plans', []) as $key => $plan) {
            DB::table('plans')->insert([
                'key' => $key, 'name' => $plan['name'], 'shopify_name' => $plan['shopify_name'] ?? $plan['name'],
                'price' => $plan['price'] ?? 0, 'sales_limit' => $plan['sales_limit'] ?? null,
                'modules' => json_encode(array_values(array_unique(array_merge(self::STOREFRONT, $plan['includes'] ?? [])))),
                'limits' => json_encode($plan['limits'] ?? []), 'features' => json_encode($plan['features'] ?? []),
                'position' => $position++, 'is_public' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('stores', function (Blueprint $table) {
            // { plan, plan_until, modules_on[], modules_off[], limits{meter: n|null}, sales_limit, note }
            $table->json('entitlements')->nullable();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_role', 20)->default('super_admin');
            $table->timestamp('disabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['admin_role', 'disabled_at']));
        Schema::dropIfExists('platform_settings');
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('entitlements'));
        Schema::dropIfExists('plans');
    }
};
