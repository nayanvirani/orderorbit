<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** G43 live chat: a chat is a support ticket with channel "chat", plus the team's online status. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('channel', 10)->default('ticket'); // ticket | chat
            $table->string('chat_state', 10)->nullable(); // waiting | active | offline | closed
            $table->string('page', 255)->nullable(); // the Growvia page the chat started on
            $table->timestamp('first_reply_at')->nullable();
            $table->smallInteger('rating')->nullable(); // 1 good, -1 not good
            $table->timestamp('merchant_typing_at')->nullable();
            $table->timestamp('team_typing_at')->nullable();
            $table->timestamp('merchant_seen_at')->nullable();
            $table->unsignedBigInteger('merchant_read_id')->default(0); // last team message the merchant has seen
            $table->timestamp('alerted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->index(['channel', 'chat_state']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('chat_status', 10)->default('away'); // online | away
            $table->timestamp('chat_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['channel', 'chat_state']);
            $table->dropColumn(['channel', 'chat_state', 'page', 'first_reply_at', 'rating', 'merchant_typing_at', 'team_typing_at', 'merchant_seen_at', 'merchant_read_id', 'alerted_at', 'closed_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['chat_status', 'chat_seen_at']));
    }
};
