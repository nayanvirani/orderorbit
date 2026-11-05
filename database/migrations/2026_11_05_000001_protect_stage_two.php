<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protect (phase 4, stage 2): the URL monitor's pages and checks (G10) and the Health Score history
 * (G12). Action Center suggestions (G13) are protect_alerts rows with severity "idea".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('kind', 20);                    // home | best_seller | collection | custom
            $table->string('label', 255)->nullable();     // product or collection title
            $table->boolean('removed')->default(false);   // a found page the merchant took off (not added back)
            $table->string('status', 10)->default('new'); // new | up | slow | down | locked
            $table->unsignedSmallInteger('last_code')->nullable();
            $table->unsignedInteger('last_ms')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->unsignedSmallInteger('fails')->default(0); // failed checks in a row
            $table->timestamp('down_since')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'url']);
        });

        Schema::create('url_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_url_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->boolean('ok');
            $table->boolean('slow')->default(false);
            $table->unsignedSmallInteger('code')->nullable();
            $table->unsignedInteger('ms')->nullable();
            $table->index(['monitored_url_id', 'checked_at']);
        });

        Schema::create('health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('day', 10);
            $table->unsignedTinyInteger('score');
            $table->json('categories');
            $table->timestamps();
            $table->unique(['store_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_scores');
        Schema::dropIfExists('url_checks');
        Schema::dropIfExists('monitored_urls');
    }
};
