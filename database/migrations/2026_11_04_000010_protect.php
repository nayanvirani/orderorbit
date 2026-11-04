<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protect (phase 4, stage 1): each variant's stock and product data next to its cost, discount
 * codes on orders, Protect settings and the money-leak alerts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_costs', function (Blueprint $table) {
            $table->decimal('previous_price', 14, 2)->nullable()->after('price');
            $table->timestamp('price_changed_at')->nullable()->after('previous_price');
            $table->string('sku', 120)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->decimal('weight', 12, 3)->nullable();
            $table->integer('available')->nullable();      // across locations
            $table->json('locations')->nullable();          // {location gid: [name, available]}
            $table->boolean('tracked')->default(false);
            $table->boolean('has_image')->default(false);    // the variant or its product has one
            $table->boolean('has_description')->default(false);
            $table->string('product_status', 20)->nullable(); // ACTIVE | DRAFT | ARCHIVED
            $table->timestamp('seen_at')->nullable();        // in Shopify at the last sync
        });

        Schema::table('profit_orders', function (Blueprint $table) {
            $table->json('codes')->nullable(); // discount codes used
        });

        Schema::table('profit_settings', function (Blueprint $table) {
            // {lead_days, cover_days, rules: {key: bool}}
            $table->json('protect')->nullable();
        });

        Schema::create('protect_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('rule', 40);
            $table->string('subject', 120); // what it's about: a variant, a product, a code, or "store"
            $table->string('severity', 10); // urgent | warning
            $table->string('title', 255);
            $table->text('what');
            $table->decimal('cost', 14, 2)->default(0); // money at risk, for sorting
            $table->string('cost_text', 255);
            $table->text('fix');
            $table->string('cta', 80)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('status', 10)->default('open'); // open | snoozed | muted | closed
            $table->timestamp('opened_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'rule', 'subject']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protect_alerts');
        Schema::table('profit_settings', fn (Blueprint $table) => $table->dropColumn('protect'));
        Schema::table('profit_orders', fn (Blueprint $table) => $table->dropColumn('codes'));
        Schema::table('product_costs', fn (Blueprint $table) => $table->dropColumn(['previous_price', 'price_changed_at', 'sku', 'barcode', 'weight', 'available', 'locations', 'tracked', 'has_image', 'has_description', 'product_status', 'seen_at']));
    }
};
