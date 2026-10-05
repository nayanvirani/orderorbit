<?php

use App\Support\LegalDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legal & policy pages, edited in the Internal Admin. `body` is what's live; `draft_*` is work in progress.
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('title', 120);
            $table->string('summary', 300)->nullable();
            $table->longText('body');
            $table->string('draft_title', 120)->nullable();
            $table->longText('draft_body')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_footer')->default(true);
            // Merchants see an in-app notice to review this page when a new version needs their attention.
            $table->boolean('requires_review')->default(false);
            $table->unsignedInteger('review_version')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('updated_by', 190)->nullable();
            $table->timestamps();
        });

        // Every published version, kept so you can show what a store agreed to and when.
        Schema::create('legal_page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 120);
            $table->longText('body');
            $table->string('change_summary', 500)->nullable();
            $table->date('effective_at')->nullable();
            $table->string('published_by', 190)->nullable();
            $table->timestamp('published_at');
            $table->unique(['legal_page_id', 'version']);
        });

        // slug => {version, at, by, via}: which version of each page the store accepted or acknowledged.
        Schema::table('stores', fn (Blueprint $table) => $table->json('legal_acks')->nullable());

        $now = now();
        $position = 0;
        foreach (LegalDefaults::pages() as $slug => [$title, $summary, $review, $footer, $body]) {
            $id = DB::table('legal_pages')->insertGetId([
                'slug' => $slug, 'title' => $title, 'summary' => $summary, 'body' => $body, 'version' => 1, 'effective_at' => $now->toDateString(),
                'is_published' => true, 'show_in_footer' => $footer, 'requires_review' => $review, 'position' => $position++,
                'updated_by' => 'system', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('legal_page_versions')->insert([
                'legal_page_id' => $id, 'version' => 1, 'title' => $title, 'body' => $body, 'change_summary' => 'First version.',
                'effective_at' => $now->toDateString(), 'published_by' => 'system', 'published_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('legal_acks'));
        Schema::dropIfExists('legal_page_versions');
        Schema::dropIfExists('legal_pages');
    }
};
