<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Announcements to merchants (G41), and who saw or closed each one. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->string('cta_text', 60)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('tone', 10)->default('info'); // info | success | warning | critical
            $table->string('placement', 12)->default('every_page'); // home | every_page
            $table->string('audience', 10)->default('all'); // all | plans | module | stores
            $table->json('plans')->nullable();
            $table->string('module', 60)->nullable();
            $table->json('store_ids')->nullable();
            $table->boolean('owners_only')->default(false);
            $table->boolean('dismissible')->default(true);
            $table->boolean('email_owners')->default(false);
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('price_change_at')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });
        Schema::create('announcement_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->timestamp('dismissed_at')->nullable();
            $table->unique(['announcement_id', 'store_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_views');
        Schema::dropIfExists('announcements');
    }
};
