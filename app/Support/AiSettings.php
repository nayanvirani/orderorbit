<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Internal Admin AI settings: on/off (off by default: the Morning Brief and offer text are written by
 * Growvia from the numbers, no AI), each plan's monthly budget per store and a request cap.
 */
class AiSettings
{
    private const KEY = 'ai-settings:v1';

    public static function get(): array
    {
        $saved = Cache::rememberForever(self::KEY, function () {
            try {
                $value = DB::table('platform_settings')->where('key', 'ai')->value('value');
            } catch (Throwable) {
                return [];
            }

            return $value ? (array) json_decode($value, true) : [];
        });

        return array_merge(['enabled' => false, 'budget_premium' => (float) env('AI_BUDGET_PREMIUM', 5), 'requests_per_store' => 300], $saved);
    }

    public static function save(array $values): void
    {
        DB::table('platform_settings')->updateOrInsert(['key' => 'ai'], ['value' => json_encode($values), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::KEY);
    }
}
