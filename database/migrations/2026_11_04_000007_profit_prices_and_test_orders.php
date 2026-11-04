<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profit: each variant's price from Shopify (for the cost finder and "% of price"), and a fresh
 * import now that development stores count their test orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_costs', function (Blueprint $table) {
            $table->decimal('price', 14, 2)->nullable()->after('title');
        });
        // Orders and prices import again on the next sync (orders are upserted, nothing is lost).
        DB::table('profit_settings')->update(['backfilled_at' => null]);
    }

    public function down(): void
    {
        Schema::table('product_costs', fn (Blueprint $table) => $table->dropColumn('price'));
    }
};
