<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal Admin: OrderOrbit team members sign in with email and password.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('description', 300)->nullable();
            $table->boolean('enabled')->default(false); // for every store
            $table->json('store_ids')->nullable(); // or only these stores
            $table->timestamps();
        });

        // Support: merchants open tickets in the app; the team answers in the Internal Admin.
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 160);
            $table->string('category', 20); // setup | publishing | analytics | workflow | extension | billing | technical
            $table->string('priority', 10)->default('normal'); // low | normal | high | urgent
            $table->string('status', 10)->default('open'); // open | pending | resolved | closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->json('diagnostics')->nullable(); // store details at the time of the ticket
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->string('last_reply_by', 10)->nullable(); // merchant | team
            $table->timestamps();
            $table->index(['store_id', 'status']);
            $table->index(['status', 'last_reply_at']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('author', 10); // merchant | team
            $table->string('author_name', 120)->nullable();
            $table->foreignId('store_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('internal')->default(false); // team-only note
            $table->timestamps();
        });

        Schema::create('support_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('support_messages')->cascadeOnDelete();
            $table->string('filename', 160);
            $table->string('mime', 80);
            $table->unsignedInteger('size');
            $table->binary('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('feature_flags');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_admin', 'last_login_at']));
    }
};
