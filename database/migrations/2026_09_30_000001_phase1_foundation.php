<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('theme_name')->nullable()->after('shopify_plan');
            $table->json('capabilities')->nullable()->after('theme_name');
            $table->timestamp('capabilities_checked_at')->nullable()->after('capabilities');
        });

        Schema::table('store_users', function (Blueprint $table) {
            $table->unsignedBigInteger('shopify_user_id')->nullable()->change();
            $table->string('first_name')->nullable()->after('shopify_user_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            $table->boolean('account_owner')->default(false)->after('email');
            $table->timestamp('invited_at')->nullable()->after('role');
            $table->foreignId('invited_by')->nullable()->after('invited_at')->constrained('store_users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable()->after('invited_by');

            $table->index(['store_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('store_users', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'email']);
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn(['first_name', 'last_name', 'email', 'account_owner', 'invited_at', 'disabled_at']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['theme_name', 'capabilities', 'capabilities_checked_at']);
        });
    }
};
