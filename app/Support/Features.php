<?php

namespace App\Support;

use App\Models\FeatureFlag;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;

/**
 * Feature flags (G40). Release flags turn a module on for chosen stores, a share of stores or
 * everyone, optionally between two dates. Kill switches (keys starting "kill.") hide a live
 * module for every store at once. Flags are declared in config/flags.php.
 */
class Features
{
    // Plain rows are cached, not models: the cache won't unserialize app classes.
    private const CACHE = 'feature-flags:v3';

    /** @return array<string, array{name: string, description: string, area: string, phase: string, built: bool, kill: bool}> */
    public static function registry(): array
    {
        $out = [];
        foreach (config('flags.release', []) as $key => [$name, $desc, $area, $phase, $built]) {
            $out[$key] = ['name' => $name, 'description' => $desc, 'area' => $area, 'phase' => $phase, 'built' => $built, 'kill' => false];
        }
        foreach (config('flags.kill', []) as $key => [$name, $desc]) {
            $out[$key] = ['name' => $name, 'description' => $desc, 'area' => 'Emergency off', 'phase' => '', 'built' => true, 'kill' => true];
        }

        return $out;
    }

    public static function enabled(string $key, ?Store $store = null): bool
    {
        $flag = self::all()[$key] ?? null;
        if (! $flag || ! $flag->inSchedule()) {
            return false;
        }

        return match ($flag->mode) {
            'all' => true,
            'stores' => $store !== null && in_array($store->id, array_map('intval', $flag->store_ids ?? []), true),
            // A stable share: the same stores stay in as the percentage grows.
            'percent' => $store !== null && (crc32($key.':'.$store->id) % 100) < $flag->percent,
            default => false,
        };
    }

    /** Emergency off: true when the kill switch for this part of Growvia is on. */
    public static function killed(string $part): bool
    {
        return self::enabled('kill.'.$part);
    }

    /** Changes when any kill switch changes, so storefront and checkout data are republished. */
    public static function killFingerprint(): string
    {
        return implode(',', array_filter(array_keys(config('flags.kill', [])), fn ($k) => self::enabled($k)));
    }

    /** @return array<string, FeatureFlag> */
    public static function all(): array
    {
        $rows = Cache::remember(self::CACHE, 60, fn () => FeatureFlag::query()->toBase()->get()->map(fn ($r) => (array) $r)->all());

        return collect($rows)->map(fn (array $r) => (new FeatureFlag)->newFromBuilder($r))->keyBy('key')->all();
    }

    public static function forget(?string $key = null): void
    {
        Cache::forget(self::CACHE);
    }
}
