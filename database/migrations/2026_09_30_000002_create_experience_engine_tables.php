<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Frontend presets (section 39). Synced from resources/experiences by orderorbit:sync-templates.
        Schema::create('cro_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('key');
            $table->string('name');
            $table->string('surface');
            $table->string('status')->default('published');
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();

            $table->unique(['type', 'key']);
        });

        Schema::create('cro_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cro_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('style');
            $table->json('schema');
            $table->json('defaults');
            $table->string('checksum', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['cro_template_id', 'version']);
        });

        // Store-level design tokens (section 40: Branding).
        Schema::create('cro_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('branding')->nullable();
            $table->timestamps();
        });

        Schema::create('cro_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('handle', 16);
            $table->string('type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('cro_template_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_key');
            $table->string('status')->default('draft');
            $table->json('draft_config');
            $table->boolean('has_unpublished_changes')->default(true);
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('placement_status')->default('unknown');
            $table->timestamp('placement_checked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('store_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('store_users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'handle']);
            $table->index(['store_id', 'type', 'status']);
        });

        Schema::create('cro_experience_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cro_experience_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('template_key');
            $table->json('config');
            $table->string('change_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('store_users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['cro_experience_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cro_experience_versions');
        Schema::dropIfExists('cro_experiences');
        Schema::dropIfExists('cro_settings');
        Schema::dropIfExists('cro_template_versions');
        Schema::dropIfExists('cro_templates');
    }
};
