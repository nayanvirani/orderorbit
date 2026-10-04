<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 10: reusable shopper segments and deterministic personalization rules.
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->string('template', 30)->nullable(); // the prebuilt segment it started from
            $table->string('match', 3)->default('all'); // all | any
            $table->json('rules'); // [{field, op, value}]
            $table->unsignedInteger('member_count')->nullable(); // from Shopify, when every rule is a customer field
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personalization_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experience_id')->constrained('cro_experiences')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('position')->default(0); // lower = higher priority
            $table->boolean('enabled')->default(true);
            $table->json('segments')->nullable(); // any of these segment ids
            $table->json('conditions')->nullable(); // live context: device, cart_min, cart_max, utm_source, utm_campaign
            $table->string('outcome', 5); // show | swap | hide
            $table->string('template_key', 64)->nullable();
            $table->timestamps();
            $table->index(['store_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalization_rules');
        Schema::dropIfExists('segments');
    }
};
