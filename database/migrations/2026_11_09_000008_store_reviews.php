<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review apps, step 2 (G30): published reviews synced daily from Judge.me or Yotpo. Only what
 * offers show: rating, title, text, the reviewer's first name, date and one photo. Reviews hidden
 * or deleted in the app are removed on the next sync; nothing can be edited in Growvia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('source', 12); // judgeme | yotpo
            $table->string('external_id', 64);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->string('author', 60)->nullable(); // first name only
            $table->string('photo', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'source', 'external_id']);
            $table->index(['store_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_reviews');
    }
};
