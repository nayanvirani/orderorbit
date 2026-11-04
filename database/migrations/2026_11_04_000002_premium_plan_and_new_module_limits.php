<?php

use App\Support\Modules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * G29: today's plans gain the next MVP's module keys and limits (added only: every existing key,
 * price and limit stays as it is, including Internal Admin edits), and the Premium plan is added
 * above Scale. Premium shows to merchants only with the premium_plan flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('plans')->get() as $row) {
            if (! isset(Modules::NEXT_MVP[$row->key]) || $row->key === 'premium') {
                continue;
            }
            $modules = json_decode((string) $row->modules, true) ?: [];
            $limits = json_decode((string) $row->limits, true) ?: [];
            DB::table('plans')->where('id', $row->id)->update([
                'modules' => json_encode(array_values(array_unique([...$modules, ...Modules::NEXT_MVP[$row->key]]))),
                'limits' => json_encode($limits + Modules::NEXT_MVP_LIMITS[$row->key]),
                'updated_at' => now(),
            ]);
        }

        $premium = (require config_path('shopify.php'))['billing']['plans']['premium'];
        if (! DB::table('plans')->where('key', 'premium')->exists()) {
            DB::table('plans')->insert([
                'key' => 'premium', 'name' => $premium['name'], 'shopify_name' => $premium['shopify_name'], 'price' => $premium['price'],
                'modules' => json_encode($premium['includes']), 'limits' => json_encode($premium['limits']), 'features' => json_encode($premium['features']),
                'description' => $premium['description'], 'support_label' => $premium['support'], 'badge' => null,
                'position' => (int) DB::table('plans')->max('position') + 1, 'is_public' => true, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Cache::forget('billing-plans:v2');
    }

    public function down(): void
    {
        DB::table('plans')->where('key', 'premium')->delete();
        Cache::forget('billing-plans:v2');
    }
};
