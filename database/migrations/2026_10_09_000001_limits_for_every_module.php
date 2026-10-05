<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A limit for every module (bundles, countdowns, checkout blocks, tests, rules…), and offers a
 * plan change pauses are marked so they come back on their own when the plan allows them again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cro_experiences', function (Blueprint $table) {
            $table->boolean('paused_by_plan')->default(false);
        });

        // Plans: every new limit from the defaults; limits already set in the admin are kept,
        // except "active experiences", which now means live offers of every type together.
        $defaults = (require config_path('shopify.php'))['billing']['plans'];
        foreach (DB::table('plans')->get(['id', 'key', 'limits']) as $plan) {
            $current = (array) json_decode((string) $plan->limits, true);
            $new = $defaults[$plan->key]['limits'] ?? null;
            if ($new === null) {
                $new = array_fill_keys(array_keys(\App\Services\Usage::METERS), null);
            }
            $merged = array_merge($new, array_intersect_key($current, $new));
            $merged['active_experiences'] = $new['active_experiences'] ?? null;
            DB::table('plans')->where('id', $plan->id)->update(['limits' => json_encode($merged), 'updated_at' => now()]);
        }
        Cache::forget('billing-plans:v2');
    }

    public function down(): void
    {
        Schema::table('cro_experiences', fn (Blueprint $table) => $table->dropColumn('paused_by_plan'));
    }
};
