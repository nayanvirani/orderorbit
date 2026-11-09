<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Profit goals and pacing (G6): {type, amount, alert, emailed} on the store's profit settings. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_settings', fn (Blueprint $table) => $table->json('goal')->nullable());
    }

    public function down(): void
    {
        Schema::table('profit_settings', fn (Blueprint $table) => $table->dropColumn('goal'));
    }
};
