<?php

namespace App\Experiences;

use InvalidArgumentException;

/**
 * The CRO experience types and their templates (resources/experiences/types.php).
 */
class Registry
{
    private static ?array $types = null;

    private static ?array $features = null;

    public static function types(): array
    {
        return self::$types ??= require resource_path('experiences/types.php');
    }

    /**
     * Types merchants can create now (retired ones keep working but live in the Bundles and
     * Progressive gifts modules).
     */
    public static function creatable(): array
    {
        return array_filter(self::types(), fn ($t) => empty($t['retired']));
    }

    public static function has(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::types());
    }

    public static function type(string $type): array
    {
        return self::types()[$type] ?? throw new InvalidArgumentException("Unknown experience type [{$type}].");
    }

    public static function templates(string $type): array
    {
        return self::type($type)['templates'];
    }

    /**
     * Templates offered for new experiences: the OrderOrbit team can unpublish a template in the
     * Internal Admin. Experiences already using it keep working (template() still finds it).
     */
    public static function offered(string $type): array
    {
        $hidden = \Illuminate\Support\Facades\Cache::remember('templates:unpublished', 300, fn () => \App\Models\CroTemplate::where('status', '!=', 'published')->get(['type', 'key'])->map(fn ($t) => $t->type.':'.$t->key)->all());

        return array_filter(self::templates($type), fn ($t, $key) => ! in_array($type.':'.$key, $hidden, true), ARRAY_FILTER_USE_BOTH) ?: self::templates($type);
    }

    public static function template(string $type, string $key): ?array
    {
        return self::templates($type)[$key] ?? null;
    }

    public static function defaultTemplate(string $type): string
    {
        return array_key_first(self::templates($type));
    }

    /**
     * Types whose active experiences count against a usage meter.
     *
     * @return array<string, string> type => meter
     */
    public static function meters(): array
    {
        return array_filter(array_map(fn ($t) => $t['meter'], self::types()));
    }

    /**
     * App features for navigation (resources/experiences/features.php), each grouping one or more types.
     */
    public static function features(): array
    {
        return self::$features ??= require resource_path('experiences/features.php');
    }

    public static function feature(string $key): ?array
    {
        return self::features()[$key] ?? null;
    }

    public static function featureFor(string $type): ?string
    {
        foreach (self::features() as $key => $feature) {
            if (in_array($type, $feature['types'], true)) {
                return $key;
            }
        }

        return null;
    }
}
