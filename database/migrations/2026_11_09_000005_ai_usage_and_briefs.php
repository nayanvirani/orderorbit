<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI features (G17 Morning Brief, G20 offer ideas): every AI request with its tokens and cost
 * (for each plan's monthly AI budget), and each store's daily Morning Brief.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 40);
            $table->string('model', 60);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost', 10, 5)->default(0); // USD
            $table->string('outcome', 20); // ok | refused | failed
            $table->timestamp('created_at');
            $table->index(['store_id', 'created_at']);
        });
        Schema::create('morning_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('day', 10);
            $table->string('status', 10); // ready | quiet | paused | failed
            $table->json('lines')->nullable();  // [{text, facts: [ids]}]
            $table->json('facts')->nullable();  // {id: {label, value, href}}
            $table->timestamps();
            $table->unique(['store_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('morning_briefs');
        Schema::dropIfExists('ai_usage');
    }
};
