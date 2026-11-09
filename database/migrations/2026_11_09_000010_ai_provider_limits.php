<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limits per AI provider (Internal Admin › AI providers): requests a minute and a day, tokens a day
 * and a month. Growvia counts each provider's use from ai_usage and moves to the next provider
 * before a limit is reached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->unsignedInteger('limit_requests_minute')->nullable()->after('price_out');
            $table->unsignedInteger('limit_requests_day')->nullable()->after('limit_requests_minute');
            $table->unsignedBigInteger('limit_tokens_day')->nullable()->after('limit_requests_day');
            $table->unsignedBigInteger('limit_tokens_month')->nullable()->after('limit_tokens_day');
        });
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_id')->nullable()->after('provider');
            $table->index(['provider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'created_at']);
            $table->dropColumn('provider_id');
        });
        Schema::table('ai_providers', fn (Blueprint $table) => $table->dropColumn(['limit_requests_minute', 'limit_requests_day', 'limit_tokens_day', 'limit_tokens_month']));
    }
};
