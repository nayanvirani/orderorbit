<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The store-sales limit is gone (plans are features and usage limits): the policies that
 * described it get a new version with the wording changed.
 */
return new class extends Migration
{
    public const CHANGES = [
        ' Plan limits, including the monthly store-sales limit, are shown in the App and on our [Pricing]({{website}}/pricing) page. The [Billing, Cancellation & Refund Policy]({{url:billing}}) explains trials, plan changes, cancellations, the sales limit and refunds.'
            => ' Each plan\'s features and usage limits are shown in the App and on our [Pricing]({{website}}/pricing) page. The [Billing, Cancellation & Refund Policy]({{url:billing}}) explains trials, plan changes, usage limits, cancellations and refunds.',
        "- Order information needed to measure your monthly store sales against your plan and to show sales pop:\n  - order ID, total, currency and date;\n  - test or cancelled status;\n  - for sales pop only, the product purchased and the shopper's country."
            => "- Order information needed to show sales pop and to run the workflows you set up:\n  - order ID and date;\n  - for sales pop, the product purchased and the shopper's country;\n  - for workflows, the order details a workflow uses (for example its total and tags).",
        "| Order totals used for plan usage | The last 3 sales cycles (about 90 days) |\n" => '',
        "## 3. Store-sales limits\n\nEach plan includes a monthly store-sales limit, measured over 30-day cycles from your install date using your store's orders.\n\n- **Test and cancelled orders:** these are not counted.\n- **Warning:** the App warns you as you get close to your plan's limit.\n- **Going over the limit:** if your store's sales pass the limit, everything keeps working for a grace period. After that, the App's storefront offers pause until you upgrade or a new cycle starts.\n- **Your data:** pausing never deletes your data or settings."
            => "## 3. Usage limits\n\nEach plan includes a set of features and usage limits, such as the number of live experiences, bundles, gift campaigns, shipping bars and workflows, and automation runs per month.\n\n- **Warning:** the App warns you as you get close to a limit, and shows your usage in Settings → Billing.\n- **At a limit:** everything already live keeps working; you can't add more until you upgrade.\n- **Your data:** limits never delete your data or settings.",
        '- try to get around plan limits, billing, the sales limit or security controls;' => '- try to get around plan limits, billing or security controls;',
    ];

    public function up(): void
    {
        foreach (DB::table('legal_pages')->get() as $page) {
            $body = str_replace(array_keys(self::CHANGES), array_values(self::CHANGES), $page->body);
            if ($body === $page->body) {
                continue;
            }
            $version = $page->version + 1;
            DB::table('legal_pages')->where('id', $page->id)->update(['body' => $body, 'version' => $version, 'effective_at' => now()->toDateString(), 'updated_by' => 'system', 'updated_at' => now()]);
            DB::table('legal_page_versions')->insert([
                'legal_page_id' => $page->id, 'version' => $version, 'title' => $page->title, 'body' => $body,
                'change_summary' => 'Plans are now features and usage limits; the store-sales limit was removed.',
                'effective_at' => now()->toDateString(), 'published_by' => 'system', 'published_at' => now(),
            ]);
        }
        \Illuminate\Support\Facades\Cache::forget('legal-pages:v1');
    }

    public function down(): void {}
};
