<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** G23 review prompt (when Shopify's review window was asked for, and what it answered) and G24 lifecycle emails. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('review_milestone', 40)->nullable();
            $table->timestamp('review_prompted_at')->nullable();
            $table->string('review_result', 60)->nullable();
            // Lifecycle emails (G24): tips can be turned off; each email is sent once.
            $table->boolean('lifecycle_unsubscribed')->default(false);
        });

        Schema::create('lifecycle_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40); // welcome | no_widget | not_placed | upgrade_preview | monthly_results:2026-10
            $table->string('to_email', 191);
            $table->string('status', 20)->default('sent'); // sent | failed
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_emails');
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['review_milestone', 'review_prompted_at', 'review_result', 'lifecycle_unsubscribed']));
    }
};
