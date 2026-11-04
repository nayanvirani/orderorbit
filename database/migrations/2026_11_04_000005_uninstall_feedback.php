<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Usage insights (G27): why stores uninstalled, from the optional question in the goodbye email. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uninstall_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 40);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uninstall_feedback');
    }
};
