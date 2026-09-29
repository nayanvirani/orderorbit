<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain')->unique();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('timezone')->nullable();
            $table->string('shopify_plan')->nullable();
            $table->string('plan')->nullable();
            $table->string('goal')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('store_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_user_id');
            $table->string('role')->default('staff');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'shopify_user_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->string('shopify_subscription_id')->unique();
            $table->string('status');
            $table->decimal('price', 8, 2);
            $table->boolean('test')->default(false);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('meter');
            $table->date('period_start');
            $table->unsignedBigInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'meter', 'period_start']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type');
            $table->string('actor_id')->nullable();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('context')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'created_at']);
        });

        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id')->unique();
            $table->string('shop_domain');
            $table->string('topic');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('store_users');
        Schema::dropIfExists('stores');
    }
};
