<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Four built-in blog posts had Google titles over 60 characters (cut off in results). Only posts
 * still using the built-in title are changed; titles edited in Internal Admin › Blog stay.
 */
return new class extends Migration
{
    private const TITLES = [
        'free-gift-with-purchase-thresholds' => ['Free Gift with Purchase on Shopify: How to Set the Right Threshold', 'Free Gift with Purchase on Shopify: Setting the Threshold'],
        'quantity-breaks-vs-bundles' => ['Quantity Breaks vs Bundles: Which Raises AOV More on Shopify?', 'Quantity Breaks vs Bundles: Which Raises AOV on Shopify?'],
        'post-purchase-automation-flows' => ['Post-Purchase Automation for Shopify: Review, Reorder & Win-back', 'Shopify Post-Purchase Flows: Review, Reorder & Win-back'],
        'subscription-bundles-shopify' => ['Subscription Bundles on Shopify: Subscribe & Save on Packs and Boxes', 'Subscription Bundles on Shopify: Subscribe & Save Packs'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }
        foreach (self::TITLES as $slug => [$old, $new]) {
            DB::table('blog_posts')->where('slug', $slug)->where('seo_title', $old)->update(['seo_title' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::TITLES as $slug => [$old, $new]) {
            DB::table('blog_posts')->where('slug', $slug)->where('seo_title', $new)->update(['seo_title' => $old]);
        }
    }
};
