<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plans are limited by the store's total sales over the last 30 days (in USD).
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('sales_30d_usd', 14, 2)->nullable();
            $table->timestamp('sales_checked_at')->nullable();
            // When the store first went over its plan's limit; offers pause after the grace period.
            $table->timestamp('over_limit_since')->nullable();
            $table->timestamp('offers_suspended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['sales_30d_usd', 'sales_checked_at', 'over_limit_since', 'offers_suspended_at']));
    }
};
