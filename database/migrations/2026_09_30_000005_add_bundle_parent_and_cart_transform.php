<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cro_experiences', function (Blueprint $table) {
            // The hidden Shopify product that bundle items merge into in the cart.
            $table->string('bundle_product_id')->nullable()->after('shopify_discount_id');
            $table->string('bundle_variant_id')->nullable()->after('bundle_product_id');
        });
        Schema::table('stores', function (Blueprint $table) {
            // The store's OrderOrbit cart transform (bundle merging).
            $table->string('cart_transform_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cro_experiences', fn (Blueprint $table) => $table->dropColumn(['bundle_product_id', 'bundle_variant_id']));
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('cart_transform_id'));
    }
};
