<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 9: A/B and A/B/C tests on published storefront experiences.
        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experience_id')->constrained('cro_experiences')->cascadeOnDelete();
            $table->string('handle', 32)->unique(); // the id shoppers' events carry
            $table->string('name', 120);
            $table->text('hypothesis')->nullable();
            $table->string('status', 12)->default('draft'); // draft | running | paused | completed | stopped
            $table->json('audience')->nullable(); // device, countries, products, collections, cart_min, cart_max, utm_source, utm_campaign, customer
            $table->string('primary_metric', 30)->default('conversion_rate');
            $table->json('secondary_metrics')->nullable();
            $table->json('guardrails')->nullable(); // [{metric, threshold}] threshold = worst acceptable change in points or %
            $table->unsignedSmallInteger('min_days')->default(7);
            $table->unsignedInteger('min_visitors')->default(1000);
            $table->unsignedInteger('min_conversions')->default(100);
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('result', 12)->nullable(); // winner | no_winner
            $table->string('winner_key', 1)->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });

        Schema::create('experiment_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->string('key', 1); // A (control), B, C
            $table->string('name', 80);
            $table->unsignedTinyInteger('allocation'); // % of the test's traffic
            $table->boolean('hidden')->default(false); // holdout: the experience isn't shown
            $table->string('template_key', 64)->nullable();
            $table->json('content')->nullable(); // text overrides
            $table->json('design')->nullable();
            $table->unique(['experiment_id', 'key']);
        });

        Schema::create('experiment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message', 300);
            $table->timestamp('created_at');
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('experiment_handle', 32)->nullable();
            $table->string('variant', 1)->nullable();
            $table->index(['store_id', 'experiment_handle']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'experiment_handle']);
            $table->dropColumn(['experiment_handle', 'variant']);
        });
        Schema::dropIfExists('experiment_logs');
        Schema::dropIfExists('experiment_variants');
        Schema::dropIfExists('experiments');
    }
};
