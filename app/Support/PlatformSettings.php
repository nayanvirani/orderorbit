<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Platform settings edited in the Internal Admin. Each one overrides a config value at boot.
 */
class PlatformSettings
{
    private const KEY = 'platform-settings:v1';

    /** key => [config path, label, type, help] */
    public const FIELDS = [
        'warn_at' => ['shopify.billing.warn_at', 'Warn merchants when a usage limit reaches', 'percent', 'Shown in the app as "You\'re close to your plan\'s limit for …", for every limit (experiences, bundles, workflows, automation runs…).'],
        'test_shops' => ['shopify.test_shops', 'Test stores (full access without a subscription)', 'list', 'One myshopify.com domain per line. They get the test plan below for free.'],
        'test_shop_plan' => ['shopify.test_shop_plan', 'Plan for test stores', 'plan', null],
    ];

    public static function boot(): void
    {
        try {
            $values = Cache::get(self::KEY);
            if ($values === null && Schema::hasTable('platform_settings')) {
                $values = DB::table('platform_settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all();
                Cache::forever(self::KEY, $values);
            }
            $values ??= [];
        } catch (Throwable) {
            return;
        }
        foreach ($values as $key => $value) {
            if (isset(self::FIELDS[$key])) {
                config([self::FIELDS[$key][0] => $value]);
            }
        }
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget(self::KEY);
        config([self::FIELDS[$key][0] => $value]);
    }
}
