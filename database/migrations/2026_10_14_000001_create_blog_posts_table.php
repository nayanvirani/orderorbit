<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blog posts, written and published in Internal Admin → Blog. Starts with the first articles
 * (resources/content/blog-posts.php), published a few days apart so the list has a real order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('category', 40)->nullable();
            $table->string('excerpt', 300)->nullable();
            $table->longText('body');
            $table->string('feature', 60)->nullable(); // related website feature page
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('status', 12)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->string('updated_by', 190)->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        $posts = require resource_path('content/blog-posts.php');
        $start = now()->subDays(3 * count($posts));
        foreach (array_values($posts) as $i => $post) {
            DB::table('blog_posts')->insert($post + [
                'status' => 'published',
                'published_at' => $start->copy()->addDays(3 * $i),
                'featured' => $i === count($posts) - 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
