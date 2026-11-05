<?php

use App\Support\LegalDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Protected customer data: the Security & Incident Response Policy is published as a new legal
 * page. The Privacy Policy and DPA updates (12-month retention of automation data, encryption at
 * rest, access logging) are loaded as drafts, for the owner to review and publish in Internal
 * Admin › Legal: published text merchants agreed to is never changed silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pages = LegalDefaults::pages();
        $now = now();
        if (! DB::table('legal_pages')->where('slug', 'security')->exists()) {
            [$title, $summary, $review, $footer, $body] = $pages['security'];
            $id = DB::table('legal_pages')->insertGetId([
                'slug' => 'security', 'title' => $title, 'summary' => $summary, 'body' => $body, 'version' => 1, 'effective_at' => $now->toDateString(),
                'is_published' => true, 'show_in_footer' => $footer, 'requires_review' => $review, 'position' => (int) DB::table('legal_pages')->max('position') + 1,
                'updated_by' => 'system', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('legal_page_versions')->insert([
                'legal_page_id' => $id, 'version' => 1, 'title' => $title, 'body' => $body, 'change_summary' => 'First version.',
                'effective_at' => $now->toDateString(), 'published_by' => 'system', 'published_at' => $now,
            ]);
        }
        foreach (['privacy', 'dpa'] as $slug) {
            DB::table('legal_pages')->where('slug', $slug)->whereNull('draft_body')
                ->update(['draft_title' => $pages[$slug][0], 'draft_body' => $pages[$slug][4], 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $id = DB::table('legal_pages')->where('slug', 'security')->value('id');
        if ($id) {
            DB::table('legal_page_versions')->where('legal_page_id', $id)->delete();
            DB::table('legal_pages')->where('id', $id)->delete();
        }
    }
};
