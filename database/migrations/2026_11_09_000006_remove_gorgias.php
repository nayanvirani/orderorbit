<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Gorgias isn't offered: removed from every plan's modules and from stores' own module overrides. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('plans')->get(['id', 'modules', 'features']) as $plan) {
            $modules = json_decode((string) $plan->modules, true) ?: [];
            $features = json_decode((string) $plan->features, true) ?: [];
            $keepFeatures = array_values(array_filter($features, fn ($f) => stripos(is_array($f) ? json_encode($f) : (string) $f, 'gorgias') === false));
            if (in_array('gorgias', $modules, true) || count($keepFeatures) !== count($features)) {
                DB::table('plans')->where('id', $plan->id)->update([
                    'modules' => json_encode(array_values(array_diff($modules, ['gorgias']))),
                    'features' => json_encode($keepFeatures), 'updated_at' => now(),
                ]);
            }
        }
        foreach (DB::table('stores')->whereNotNull('entitlements')->get(['id', 'entitlements']) as $store) {
            $e = json_decode((string) $store->entitlements, true) ?: [];
            $changed = false;
            foreach (['modules_on', 'modules_off'] as $key) {
                if (in_array('gorgias', (array) ($e[$key] ?? []), true)) {
                    $e[$key] = array_values(array_diff($e[$key], ['gorgias']));
                    $changed = true;
                }
            }
            if ($changed) {
                DB::table('stores')->where('id', $store->id)->update(['entitlements' => json_encode($e)]);
            }
        }
        Cache::forget('billing-plans:v2');
    }

    public function down(): void {}
};
