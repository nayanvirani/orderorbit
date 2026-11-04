<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profit: keep whether each order is a test order or cancelled, so a development store that goes
 * live drops its test orders from profit (and a store's numbers follow its type).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_orders', function (Blueprint $table) {
            $table->boolean('test')->default(false)->after('excluded');
            $table->boolean('cancelled')->default(false)->after('test');
        });
        DB::table('profit_settings')->update(['backfilled_at' => null]); // re-import to fill both
    }

    public function down(): void
    {
        Schema::table('profit_orders', fn (Blueprint $table) => $table->dropColumn(['test', 'cancelled']));
    }
};
