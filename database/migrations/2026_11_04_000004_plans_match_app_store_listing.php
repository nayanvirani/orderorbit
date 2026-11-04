<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The plans now match the live App Store listing exactly (4 Nov 2026): limits and the "What's
 * included" lines. Only the listed limits change; every other saved setting stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plans = (require config_path('shopify.php'))['billing']['plans'];
        $listed = [
            'free' => ['active_experiences', 'bundles', 'free_gifts', 'countdowns', 'sticky_atc', 'preorders', 'workflows', 'automation_executions'],
            'starter' => ['active_experiences', 'bundles', 'free_gifts', 'shipping_bars', 'countdowns', 'trust', 'preorders', 'workflows', 'automation_executions'],
            'growth' => ['active_experiences', 'running_tests', 'personalization_rules', 'segments', 'workflows', 'automation_executions'],
            'scale' => ['automation_executions'],
            'premium' => ['automation_executions'],
        ];
        foreach ($listed as $key => $meters) {
            $row = DB::table('plans')->where('key', $key)->first();
            if (! $row) {
                continue;
            }
            $limits = json_decode((string) $row->limits, true) ?: [];
            foreach ($meters as $meter) {
                $limits[$meter] = $plans[$key]['limits'][$meter];
            }
            DB::table('plans')->where('key', $key)->update(['limits' => json_encode($limits), 'features' => json_encode($plans[$key]['features']), 'updated_at' => now()]);
        }
        Cache::forget('billing-plans:v2');
    }

    public function down(): void {}
};
