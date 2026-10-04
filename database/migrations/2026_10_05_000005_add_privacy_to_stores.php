<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings → Privacy: retention and event collection controls.
        Schema::table('stores', fn (Blueprint $table) => $table->json('privacy')->nullable());
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('privacy'));
    }
};
