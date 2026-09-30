<?php

namespace App\Services\Experiences;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Models\CroTemplate;
use App\Models\CroTemplateVersion;
use Illuminate\Support\Facades\DB;

/**
 * Keeps cro_templates in step with the type registry. A template gets a new
 * version whenever its schema, style or defaults change, so experiences keep
 * pointing at the version they were built from (section 39).
 */
class TemplateLibrary
{
    /** Design presets per renderer style; branding colours win except where noted. */
    public const STYLE_PRESETS = [
        'minimal' => ['border' => false, 'radius' => 8, 'spacing' => 'compact'],
        'card' => ['border' => true, 'radius' => 14],
        'banner' => ['border' => false, 'radius' => 10],
        'premium' => ['border' => false, 'radius' => 18, 'spacing' => 'spacious', 'background_color' => '#1d1b33', 'text_color' => '#ffffff', 'accent_color' => '#c9a45c'],
        'compact' => ['border' => true, 'radius' => 8, 'spacing' => 'compact'],
        'ladder' => ['border' => true, 'radius' => 14],
        'grid' => ['border' => true, 'radius' => 12],
        'carousel' => ['border' => true, 'radius' => 12],
        'slider' => ['border' => true, 'radius' => 14],
        'table' => ['border' => true, 'radius' => 10],
        'row' => ['border' => false, 'radius' => 10, 'spacing' => 'compact'],
    ];

    /**
     * @return array{created: int, versioned: int}
     */
    public function sync(): array
    {
        $stats = ['created' => 0, 'versioned' => 0];

        DB::transaction(function () use (&$stats) {
            foreach (Registry::types() as $type => $definition) {
                foreach ($definition['templates'] as $key => $template) {
                    $record = CroTemplate::firstOrNew(['type' => $type, 'key' => $key]);
                    $isNew = ! $record->exists;
                    $record->fill(['name' => $template['name'], 'surface' => $definition['surface'], 'status' => 'published'])->save();
                    $stats['created'] += $isNew ? 1 : 0;

                    $schema = Schema::fields($type);
                    $defaults = self::defaults($type, $key);
                    $checksum = hash('sha256', json_encode([$template['style'], $schema, $defaults]));

                    $latest = $record->versions()->orderByDesc('version')->first();
                    if ($latest?->checksum === $checksum) {
                        continue;
                    }

                    $version = ($latest?->version ?? 0) + 1;
                    CroTemplateVersion::create([
                        'cro_template_id' => $record->id,
                        'version' => $version,
                        'style' => $template['style'],
                        'schema' => $schema,
                        'defaults' => $defaults,
                        'checksum' => $checksum,
                        'published_at' => now(),
                    ]);
                    $record->forceFill(['current_version' => $version])->save();
                    $stats['versioned'] += $latest ? 1 : 0;
                }
            }
        });

        return $stats;
    }

    /**
     * Template defaults before branding: the type defaults plus the style preset.
     */
    public static function defaults(string $type, string $templateKey, array $branding = []): array
    {
        $template = Registry::template($type, $templateKey) ?? [];
        $style = $template['style'] ?? 'card';
        $config = Schema::defaults($type, $branding);
        $config['content'] = array_merge($config['content'], $template['content'] ?? []);
        $preset = self::STYLE_PRESETS[$style] ?? [];

        // Premium templates keep their own dark palette; other presets never override branding colours.
        if ($style !== 'premium') {
            $preset = array_diff_key($preset, array_flip(['background_color', 'text_color', 'accent_color', 'primary_color']));
        }
        $config['design'] = array_merge($config['design'], $preset);

        return $config;
    }

    /**
     * A render-ready template preview for the app (template pickers and galleries).
     * Empty product pickers get sample products so the template shows its layout.
     */
    public static function preview(string $type, string $templateKey, array $branding = []): array
    {
        $config = self::defaults($type, $templateKey, $branding);
        foreach (Registry::type($type)['content'] as $name => $field) {
            if ($field['type'] === 'products' && empty($config['content'][$name])) {
                $config['content'][$name] = array_slice(self::SAMPLE_PRODUCTS, 0, min(3, $field['max_items'] ?? 3));
            }
        }

        return ['id' => "tpl-{$type}-{$templateKey}", 'type' => $type, 'template' => $templateKey, 'style' => Registry::template($type, $templateKey)['style'] ?? 'card', 'version' => 0, 'priority' => 0] + $config;
    }

    private const SAMPLE_PRODUCTS = [
        ['id' => 'gid://shopify/Product/1', 'title' => 'Glow Serum', 'price' => 29.0, 'compare_at' => 36.0],
        ['id' => 'gid://shopify/Product/2', 'title' => 'Night Cream', 'price' => 34.0],
        ['id' => 'gid://shopify/Product/3', 'title' => 'Gentle Cleanser', 'price' => 18.0],
    ];

    public static function currentVersion(string $type, string $key): ?CroTemplateVersion
    {
        return CroTemplateVersion::whereHas('template', fn ($q) => $q->where('type', $type)->where('key', $key))
            ->orderByDesc('version')
            ->first();
    }
}
