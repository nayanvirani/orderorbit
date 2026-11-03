<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Feature flags v2 (G40): who gets a flag (off, chosen stores, a share of stores, everyone) and when. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feature_flags', function (Blueprint $table) {
            $table->string('mode', 10)->default('off'); // off | stores | percent | all
            $table->unsignedTinyInteger('percent')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
        });
        foreach (DB::table('feature_flags')->get() as $f) {
            $ids = json_decode((string) $f->store_ids, true) ?: [];
            DB::table('feature_flags')->where('id', $f->id)->update(['mode' => $f->enabled ? 'all' : ($ids ? 'stores' : 'off')]);
        }
    }

    public function down(): void
    {
        Schema::table('feature_flags', function (Blueprint $table) {
            $table->dropColumn(['mode', 'percent', 'starts_at', 'ends_at']);
        });
    }
};
