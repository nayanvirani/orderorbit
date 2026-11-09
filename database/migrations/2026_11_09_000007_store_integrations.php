<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrations (phase 6): one row per connected app and store. Keys are encrypted at rest and
 * deleted on disconnect; the last 50 syncs or errors are kept for Settings › Integrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20); // ga4 | klaviyo | judgeme | yotpo
            $table->text('credentials')->nullable(); // encrypted
            $table->json('settings')->nullable();
            $table->string('status', 12)->default('connected'); // connected | failing
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('log')->nullable(); // newest first: [{at, text, ok}]
            $table->timestamps();
            $table->unique(['store_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_integrations');
    }
};
