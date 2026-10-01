<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Shopify order, for the plan's sales limit. Totals only: no customer details.
        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_order_id', 40);
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->decimal('amount_usd', 14, 2)->default(0);
            $table->boolean('test')->default(false);
            $table->boolean('cancelled')->default(false);
            $table->timestamp('ordered_at');
            $table->timestamp('synced_at')->nullable();
            $table->unique(['store_id', 'shopify_order_id']);
            $table->index(['store_id', 'ordered_at']);
        });

        // A closed 30-day cycle: what the store sold and how it stood against its plan.
        Schema::create('sales_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->decimal('sales_usd', 14, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->string('plan', 40)->nullable();
            $table->decimal('sales_limit', 14, 2)->nullable();
            $table->boolean('over_limit')->default(false);
            $table->timestamps();
            $table->unique(['store_id', 'starts_at']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->renameColumn('sales_30d_usd', 'cycle_sales_usd');
        });
        Schema::table('stores', function (Blueprint $table) {
            // Cycles are 30 days long, counted from the first install. A reinstall doesn't restart them.
            $table->timestamp('cycle_anchor_at')->nullable();
            $table->timestamp('cycle_started_at')->nullable();
            // The plan the store was on when it went over; only moving to a higher plan clears it.
            $table->string('over_limit_plan', 40)->nullable();
        });

        DB::table('stores')->update(['cycle_anchor_at' => DB::raw('COALESCE(installed_at, created_at)'), 'cycle_sales_usd' => null, 'sales_checked_at' => null]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['cycle_anchor_at', 'cycle_started_at', 'over_limit_plan']);
        });
        Schema::table('stores', function (Blueprint $table) {
            $table->renameColumn('cycle_sales_usd', 'sales_30d_usd');
        });
        Schema::dropIfExists('sales_cycles');
        Schema::dropIfExists('store_orders');
    }
};
