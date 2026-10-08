<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Identifies the store's web pixel posts; the pixel itself is registered in Shopify.
            $table->string('pixel_token', 64)->nullable();
            $table->string('web_pixel_id')->nullable();
        });

        // Storefront analytics from the Growvia web pixel (no personal data).
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('experience_handle', 64)->nullable();
            // session | view | click | add | order (store-level order) | attributed (revenue credited to an experience)
            $table->string('event', 20);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('order_ref', 120)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['store_id', 'occurred_at']);
            $table->index(['store_id', 'experience_handle', 'event']);
            $table->unique(['store_id', 'order_ref', 'event', 'experience_handle'], 'analytics_order_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['pixel_token', 'web_pixel_id']));
    }
};
