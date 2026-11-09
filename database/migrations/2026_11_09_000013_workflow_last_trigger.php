<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened the last time an order or customer event met each workflow: a run started, or
 * why it didn't (plan, monthly run limit, trigger settings, not published). Shown in the editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_workflows', fn (Blueprint $table) => $table->json('last_trigger')->nullable()->after('last_run_at'));
    }

    public function down(): void
    {
        Schema::table('automation_workflows', fn (Blueprint $table) => $table->dropColumn('last_trigger'));
    }
};
