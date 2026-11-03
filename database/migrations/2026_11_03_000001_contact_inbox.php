<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contact inbox (G25): read and replied state, the team's reply and the matching store. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reply')->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replied_by');
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn(['read_at', 'replied_at', 'reply']);
        });
    }
};
