<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Public-website content: built-in copy kept as plain PHP arrays in resources/content, with the
 * edits made in the super admin (Website content, App\Support\SiteContent) applied on top.
 */
class Content
{
    private static array $cache = [];

    public static function features(): array
    {
        return SiteContent::applyItems('feature', self::baseFeatures());
    }

    /** The built-in feature copy; long-form copy (status, summary, overview, benefits) lives in feature-copy.php. */
    public static function baseFeatures(): array
    {
        $features = self::base('features');
        $copy = self::base('feature-copy');
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
        return SiteContent::applyItems('solution', self::base('solutions'));
    }

    public static function solution(string $slug): ?array
    {
        $solution = self::solutions()[$slug] ?? null;

        return $solution ? ['slug' => $slug] + $solution : null;
    }

    /** The app's templates (App\Support\TemplateGallery), so the website shows exactly what merchants get. */
    public static function templates(): array
    {
        return TemplateGallery::all();
    }

    public static function posts(): array
    {
        return array_map(fn ($post) => $post + ['slug' => Str::slug($post['title'])], SiteContent::applyList('blog', self::base('blog')));
    }

    /** Documentation guides (/docs). */
    public static function docs(): array
    {
        return SiteContent::applyItems('guide', self::base('docs'));
    }

    public static function helpCategories(): array
    {
        return array_map(fn ($c) => $c + ['slug' => Str::slug($c['name'])], SiteContent::applyList('help', self::base('help')));
    }

    public static function pricing(): array
    {
        return SiteContent::applyList('pricing', self::base('pricing'));
    }

    /** A content file as written, without admin edits. */
    public static function base(string $name): array
    {
        return self::$cache[$name] ??= require resource_path("content/{$name}.php");
    }
}
