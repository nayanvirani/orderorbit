<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff access (G28): which parts of Growvia each staff member can open, and the weekly email (G16). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_users', function (Blueprint $table) {
            $table->json('access')->nullable(); // {offers, analytics, profit, insights: bool}; missing = the role's default
            $table->boolean('weekly_email')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('store_users', fn (Blueprint $table) => $table->dropColumn(['access', 'weekly_email']));
    }
};
