<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A short answer for events that carry one, such as a post-purchase survey choice.
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('label', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', fn (Blueprint $table) => $table->dropColumn('label'));
    }
};
