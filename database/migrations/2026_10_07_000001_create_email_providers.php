<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Email services (Brevo, Resend, SMTP, ...) set up in the super admin. Several at once:
        // when one reaches its limit or fails, sending moves to the next.
        Schema::create('email_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('driver', 30);
            $table->text('credentials'); // encrypted JSON
            $table->string('from_email', 190)->nullable();
            $table->string('from_name', 120)->nullable();
            $table->unsignedInteger('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('monthly_limit')->nullable();
            $table->unsignedInteger('sent_today')->default(0);
            $table->unsignedInteger('sent_month')->default(0);
            $table->date('usage_day')->nullable();
            $table->string('usage_month', 7)->nullable();
            $table->string('status', 20)->default('ok'); // ok | limited | failing
            $table->timestamp('paused_until')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        // Every send attempt that reached a provider, for the super admin's delivery log.
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name', 80)->nullable();
            $table->string('category', 30); // automation | test | system
            $table->string('to_email', 191);
            $table->string('subject', 200);
            $table->string('status', 20); // sent | failed
            $table->text('error')->nullable();
            $table->string('message_id', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('automation_emails', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('message_id', 200)->nullable();
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::table('automation_emails', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_attempt_at']);
            $table->dropColumn(['attempts', 'next_attempt_at', 'message_id']);
        });
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('email_providers');
    }
};
