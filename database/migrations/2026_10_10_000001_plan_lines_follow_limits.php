<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Plan feature lines that quote a limit now read it from the plan ({bundles|bundle}), so editing a
 * limit in the admin also updates the pricing cards. Only lines still as written by default are
 * changed; lines edited in the admin are kept.
 */
return new class extends Migration
{
    private const OLD = [
        'free' => ['Any 4 widgets live at once', '1 bundle, 1 gift & 1 shipping bar', 'Countdown timer & sticky add to cart', 'Trust badges & sales pop', '1 pre-order campaign', '1 automation workflow, 50 runs/month', 'Basic analytics: revenue per widget', 'Every template included'],
        'starter' => ['15 widgets live at once', '3 bundles with quantity breaks', '2 free-gift campaigns & 2 shipping bars', 'Product & cart upsells', '5 countdowns, trust & pre-order widgets', '5 workflows, 500 runs/month', 'Basic analytics: revenue per widget', 'Everything in Free'],
        'growth' => ['Unlimited widgets & bundles', 'Checkout & Thank You page blocks', 'Post-purchase one-click upsells', 'A/B/C tests with guardrails (5 live)', 'Funnels, attribution & journeys', 'Personalization by segment & UTM', 'Unlimited workflows, 3,000 runs/mo', 'Priority support'],
        'scale' => ['Everything in Growth', 'Unlimited A/B tests', 'Advanced targeting: VIP, device, cart', 'Customer account blocks', 'Reorder, rewards & reviews in accounts', '10,000 automation runs/month', 'Unlimited segments & rules', 'Priority enhanced support'],
    ];

    public function up(): void
    {
        $defaults = (require config_path('shopify.php'))['billing']['plans'];
        foreach (DB::table('plans')->get(['id', 'key', 'features']) as $plan) {
            $old = self::OLD[$plan->key] ?? null;
            $new = $defaults[$plan->key]['features'] ?? null;
            if (! $old || ! $new) {
                continue;
            }
            $lines = (array) json_decode((string) $plan->features, true);
            $lines = array_map(fn ($line) => ($i = array_search($line, $old, true)) !== false ? $new[$i] : $line, $lines);
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode(array_values($lines)), 'updated_at' => now()]);
        }
        Cache::forget('billing-plans:v2');
    }

    public function down(): void {}
};
