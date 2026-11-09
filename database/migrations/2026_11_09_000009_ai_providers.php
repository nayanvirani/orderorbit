<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI providers (Internal Admin › AI providers): the services AI features go through, tried in
 * order with failover, like email providers. Keys are encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('preset', 20);               // anthropic | groq | gemini | openrouter | mistral | deepseek | together | custom
            $table->string('driver', 12);               // anthropic | openai (OpenAI-compatible chat completions)
            $table->string('base_url', 300)->nullable();
            $table->string('model', 120);
            $table->text('credentials');                // encrypted {api_key}
            $table->decimal('price_in', 10, 4)->default(0);  // USD per million input tokens (0 for free tiers)
            $table->decimal('price_out', 10, 4)->default(0);
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->string('status', 10)->default('ok'); // ok | failing
            $table->string('last_error', 500)->nullable();
            $table->timestamp('paused_until')->nullable(); // resting after a rate limit or error
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('requests_month')->default(0);
            $table->string('month', 7)->nullable();
            $table->timestamps();
        });
        Schema::table('ai_usage', fn (Blueprint $table) => $table->string('provider', 80)->nullable()->after('feature'));
    }

    public function down(): void
    {
        Schema::table('ai_usage', fn (Blueprint $table) => $table->dropColumn('provider'));
        Schema::dropIfExists('ai_providers');
    }
};
