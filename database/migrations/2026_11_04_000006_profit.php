<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profit (phase 3: G1–G5, G14): orders and their lines synced from Shopify (amounts in the shop's
 * currency), product costs, cost rules, ad expenses and a daily profit summary the pages read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('name', 40)->nullable();
            $table->timestamp('processed_at');
            $table->date('day'); // in the store's time zone
            $table->string('currency', 3)->nullable();
            $table->string('channel', 60)->nullable();
            $table->string('country', 2)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->boolean('returning')->default(false);
            $table->string('gateway', 80)->nullable();
            $table->decimal('gross', 14, 2)->default(0);      // items at their price, before discounts
            $table->decimal('discounts', 14, 2)->default(0);
            $table->decimal('refunded', 14, 2)->default(0);   // refunded items, before tax
            $table->decimal('shipping', 14, 2)->default(0);   // shipping charged, after shipping refunds
            $table->decimal('taxes', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);      // what the customer paid
            $table->boolean('excluded')->default(false);       // cancelled or test orders
            $table->timestamps();
            $table->unique(['store_id', 'order_id']);
            $table->index(['store_id', 'day']);
        });

        Schema::create('profit_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profit_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('title', 255)->nullable();
            $table->string('variant_title', 255)->nullable();
            $table->string('sku', 120)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('refunded_quantity')->default(0);
            $table->decimal('price', 14, 2)->default(0);      // one unit
            $table->decimal('discount', 14, 2)->default(0);   // the whole line
            $table->decimal('refunded', 14, 2)->default(0);   // the whole line, before tax
            $table->index(['store_id', 'variant_id']);
            $table->index(['store_id', 'product_id']);
        });

        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('title', 255)->nullable();
            $table->decimal('shopify_cost', 14, 2)->nullable(); // Shopify's "Cost per item" (read only)
            $table->decimal('cost', 14, 2)->nullable();         // set in Growvia; wins over Shopify's
            $table->string('source', 20)->nullable();           // manual | csv | percent
            $table->timestamps();
            $table->unique(['store_id', 'variant_id']);
        });

        Schema::create('profit_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            // {percent, fixed, countries: {CC: {percent, fixed}}}
            $table->json('fees')->nullable();
            // {mode: none|per_order|per_item|percent, value}
            $table->json('shipping')->nullable();
            // [{name, amount}] per month
            $table->json('fixed_costs')->nullable();
            $table->decimal('default_cost_percent', 5, 2)->nullable(); // cost as % of price when none is set
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('costs_synced_at')->nullable();
            $table->timestamp('backfilled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // with recurring: last month (null = ongoing)
            $table->boolean('recurring')->default(false); // the amount repeats every month
            $table->string('channel', 30);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->nullable();
            $table->string('note', 255)->nullable();
            $table->json('tags')->nullable(); // [{type: product|collection, id, title}]
            $table->timestamps();
            $table->index(['store_id', 'starts_on']);
        });

        Schema::create('profit_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('units')->default(0);
            foreach (['gross', 'discounts', 'refunded', 'net_sales', 'shipping', 'cogs', 'missing_cost_sales', 'fees', 'shipping_cost',
                'ad_spend', 'fixed_costs', 'gross_profit', 'contribution', 'profit'] as $column) {
                $table->decimal($column, 14, 2)->default(0);
            }
            $table->timestamps();
            $table->unique(['store_id', 'day']);
        });
    }

    public function down(): void
    {
        foreach (['profit_daily', 'ad_expenses', 'profit_settings', 'product_costs', 'profit_order_lines', 'profit_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
