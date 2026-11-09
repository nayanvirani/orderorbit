<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Widget translations (G22): {locale: {content path: [original text, translation]}} per offer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cro_experiences', fn (Blueprint $table) => $table->json('translations')->nullable());
    }

    public function down(): void
    {
        Schema::table('cro_experiences', fn (Blueprint $table) => $table->dropColumn('translations'));
    }
};
