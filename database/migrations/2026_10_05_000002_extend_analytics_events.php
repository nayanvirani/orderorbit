<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 8: the event model behind Event Explorer, Funnels, Attribution and Customer Journey.
        // Still no names, emails or addresses: visitors are Shopify's anonymous client id, customers
        // only their numeric id (with the shopper's analytics consent).
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('name', 60)->nullable(); // page_viewed, product_viewed, orderorbit:bundle_viewed, …
            $table->string('visitor_id', 64)->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('customer_id', 32)->nullable();
            $table->string('experience_type', 40)->nullable();
            $table->string('template', 64)->nullable();
            $table->string('page_type', 20)->nullable();
            $table->string('product_id', 32)->nullable();
            $table->string('variant_id', 32)->nullable();
            $table->string('device', 10)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('source', 60)->nullable(); // utm_source, the referring site, or "direct"
            $table->string('medium', 60)->nullable();
            $table->string('campaign', 100)->nullable();
            $table->json('properties')->nullable();
            $table->index(['store_id', 'name', 'occurred_at']);
            $table->index(['store_id', 'visitor_id', 'occurred_at']);
            $table->index(['store_id', 'customer_id']);
        });

        // Events recorded before Phase 8 get their catalogue names.
        foreach ([
            'session' => 'session_started', 'order' => 'checkout_completed', 'attributed' => 'orderorbit:revenue_attributed',
            'view' => 'orderorbit:experience_viewed', 'click' => 'orderorbit:experience_clicked', 'add' => 'orderorbit:added_to_cart',
            'unlock' => 'orderorbit:reward_unlocked', 'accept' => 'orderorbit:upsell_accepted', 'decline' => 'orderorbit:upsell_declined',
            'survey' => 'orderorbit:survey_answered',
        ] as $event => $name) {
            DB::table('analytics_events')->where('event', $event)->whereNull('name')->update(['name' => $name]);
        }

        Schema::create('analytics_funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('steps'); // [{event, experience?}]
            $table->string('within', 10)->default('7d'); // session | 1d | 7d | 30d
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_funnels');
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'name', 'occurred_at']);
            $table->dropIndex(['store_id', 'visitor_id', 'occurred_at']);
            $table->dropIndex(['store_id', 'customer_id']);
            $table->dropColumn(['name', 'visitor_id', 'session_id', 'customer_id', 'experience_type', 'template', 'page_type', 'product_id', 'variant_id', 'device', 'country', 'source', 'medium', 'campaign', 'properties']);
        });
    }
};
