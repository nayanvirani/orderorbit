<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store questions (G18): questions added from Internal Admin (on top of the built-in library),
 * each person's favourites, and every question asked (recently asked and the Free plan's 10 a month).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_questions', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 40);
            $table->string('text', 200);
            $table->json('spec'); // the same building blocks as the library
            $table->boolean('active')->default(true);
            $table->string('created_by', 190)->nullable();
            $table->timestamps();
        });
        Schema::create('store_question_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_user_id')->constrained()->cascadeOnDelete();
            $table->string('question', 120);
            $table->timestamps();
            $table->unique(['store_user_id', 'question']);
        });
        Schema::create('store_question_asks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question', 120);
            $table->timestamp('asked_at');
            $table->index(['store_id', 'asked_at']);
            $table->index(['store_user_id', 'asked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_question_asks');
        Schema::dropIfExists('store_question_favourites');
        Schema::dropIfExists('store_questions');
    }
};
