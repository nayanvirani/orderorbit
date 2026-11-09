<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who wrote a Morning Brief: Growvia from the numbers (auto) or AI (ai). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('morning_briefs', fn (Blueprint $table) => $table->string('source', 4)->nullable()->after('status'));
    }

    public function down(): void
    {
        Schema::table('morning_briefs', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
