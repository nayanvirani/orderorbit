<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real recent purchases for Sales pop, from the web pixel. Product details and the
        // order's country only: no names, emails or addresses.
        Schema::create('recent_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('order_ref', 120);
            $table->string('product_id', 40);
            $table->string('title', 255);
            $table->string('url', 500)->nullable();
            $table->string('image', 1000)->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamp('purchased_at');
            $table->index(['store_id', 'purchased_at']);
            $table->unique(['store_id', 'order_ref', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recent_purchases');
    }
};
