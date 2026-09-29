<?php

namespace App\Experiences;

use InvalidArgumentException;

/**
 * The CRO experience types and their templates (resources/experiences/types.php).
 */
class Registry
{
    private static ?array $types = null;

    public static function types(): array
    {
        return self::$types ??= require resource_path('experiences/types.php');
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
}
