<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Public-website content, kept as plain PHP arrays in resources/content.
 */
class Content
{
    private static array $cache = [];

    public static function features(): array
    {
        // Long-form copy (status, summary, overview, benefits) lives in feature-copy.php.
        $features = self::load('features');
        $copy = self::load('feature-copy');
        foreach ($features as $slug => $feature) {
            $features[$slug] = $feature + ($copy[$slug] ?? []);
        }

        return $features;
    }

    public static function feature(string $slug): ?array
    {
        $feature = self::features()[$slug] ?? null;

        return $feature ? ['slug' => $slug] + $feature : null;
    }

    /**
     * @return array<string, array<string, array>> features keyed by group
     */
    public static function featureGroups(): array
    {
        $groups = ['convert' => [], 'checkout' => [], 'grow' => []];

        foreach (self::features() as $slug => $feature) {
            $groups[$feature['group']][$slug] = $feature;
        }

        return $groups;
    }

    public static function solutions(): array
    {
        return self::load('solutions');
    }

    public static function solution(string $slug): ?array
    {
        $solution = self::solutions()[$slug] ?? null;

        return $solution ? ['slug' => $slug] + $solution : null;
    }

    public static function templates(): array
    {
        return self::load('templates');
    }

    public static function posts(): array
    {
        return array_map(fn ($post) => $post + ['slug' => Str::slug($post['title'])], self::load('blog'));
    }

    /** Documentation guides (/docs). */
    public static function docs(): array
    {
        return self::load('docs');
    }

    public static function helpCategories(): array
    {
        return array_map(fn ($c) => $c + ['slug' => Str::slug($c['name'])], self::load('help'));
    }

    public static function pricing(): array
    {
        return self::load('pricing');
    }

    private static function load(string $name): array
    {
        return self::$cache[$name] ??= require resource_path("content/{$name}.php");
    }
}
