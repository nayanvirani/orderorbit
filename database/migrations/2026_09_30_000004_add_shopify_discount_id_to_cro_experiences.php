<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cro_experiences', function (Blueprint $table) {
            // The Shopify automatic discount that applies this experience's saving at checkout.
            $table->string('shopify_discount_id')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('cro_experiences', function (Blueprint $table) {
            $table->dropColumn('shopify_discount_id');
        });
    }
};
