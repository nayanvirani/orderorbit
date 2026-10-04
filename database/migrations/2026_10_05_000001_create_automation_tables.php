<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A workflow: a trigger and a list of steps (conditions, waits, actions, branches).
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('handle', 40)->unique();
            $table->string('name', 120);
            $table->string('template', 60)->nullable();
            $table->string('status', 20)->default('draft'); // draft | enabled | disabled
            $table->string('trigger', 40);
            $table->json('draft');
            $table->boolean('has_unpublished_changes')->default(true);
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status', 'trigger']);
        });

        // Published snapshots; runs always follow the version they started on.
        Schema::create('automation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->json('program'); // the steps compiled into a flat list with jumps
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('published_at');
            $table->unique(['workflow_id', 'version']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('automation_versions')->cascadeOnDelete();
            $table->string('trigger', 40);
            // One run per workflow and trigger event, however often Shopify sends the webhook.
            $table->string('idempotency_key', 191);
            $table->string('subject', 120)->nullable(); // e.g. "Order #1052"
            $table->string('status', 20)->default('running'); // running | waiting | completed | failed | skipped
            $table->unsignedInteger('step')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('depth')->default(0); // "trigger another workflow" nesting
            $table->json('context');
            $table->json('results')->nullable();
            $table->timestamp('resume_at')->nullable();
            $table->text('error')->nullable();
            $table->boolean('test')->default(false);
            $table->timestamps();
            $table->timestamp('finished_at')->nullable();
            $table->unique(['store_id', 'idempotency_key']);
            $table->index(['status', 'resume_at']);
            $table->index(['store_id', 'created_at']);
        });

        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('automation_runs')->cascadeOnDelete();
            $table->unsignedInteger('step');
            $table->string('kind', 30); // the step type or action
            $table->string('status', 20); // ok | skipped | failed | waiting
            $table->string('message', 500);
            $table->json('data')->nullable();
            $table->timestamp('created_at');
        });

        // Tasks and notifications for the store's team, created by workflows.
        Schema::create('automation_inbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
            $table->string('kind', 20); // task | notification
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'done_at']);
        });

        // Emails a workflow prepared. Sending is set up later (provider chosen in super admin).
        Schema::create('automation_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
            $table->string('customer_id', 40)->nullable();
            $table->string('to_email', 191)->nullable();
            $table->string('subject', 200);
            $table->text('body');
            $table->string('status', 30)->default('waiting_for_provider'); // waiting_for_provider | sent | failed
            $table->string('provider', 30)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->timestamp('sent_at')->nullable();
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_emails');
        Schema::dropIfExists('automation_inbox');
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_versions');
        Schema::dropIfExists('automation_workflows');
    }
};
