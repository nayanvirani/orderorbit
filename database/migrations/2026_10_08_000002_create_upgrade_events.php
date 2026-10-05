<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A merchant clicked "Upgrade" from a locked feature or a full limit; converted when the
        // store moves to a higher plan within 7 days. Shows what drives upgrades.
        Schema::create('upgrade_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 60);
            $table->string('from_plan', 40)->nullable();
            $table->string('to_plan', 40)->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['store_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upgrade_events');
    }
};
