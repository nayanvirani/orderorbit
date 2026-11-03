<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ops alerts for the Growvia team (G26): one row per problem while it lasts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80);
            $table->string('title', 200);
            $table->text('details')->nullable();
            $table->unsignedInteger('seen')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_emailed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['key', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_alerts');
    }
};
