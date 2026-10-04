<?php

namespace App\Services\Experiences;

use App\Experiences\BundleSchema;
use App\Experiences\GiftSchema;
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
            // Two deploys can start together (e.g. a push and a variable change); take turns.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(4711001)');
            }
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
        if ($type === 'bundles') {
            return BundleSchema::defaults($templateKey, $branding);
        }
        if ($type === 'progressive-gifts') {
            return GiftSchema::defaults($templateKey, $branding);
        }

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
        // Templates may also preset design fields of their own (checkout box styles, for example).
        $config['design'] = array_merge($config['design'], array_intersect_key($template['design'] ?? [], $config['design']));

        return $config;
    }

    /**
     * A render-ready template preview for the app (template pickers and galleries).
     * Empty product pickers get sample products so the template shows its layout.
     */
    public static function preview(string $type, string $templateKey, array $branding = []): array
    {
        if ($type === 'bundles') {
            return self::bundlePreview($templateKey, $branding);
        }
        if ($type === 'progressive-gifts') {
            return self::giftPreview($templateKey);
        }

        $config = self::defaults($type, $templateKey, $branding);
        foreach (Registry::type($type)['content'] as $name => $field) {
            if ($field['type'] === 'products' && empty($config['content'][$name])) {
                $config['content'][$name] = array_slice(self::samples(), 0, min(3, $field['max_items'] ?? 3));
            }
        }

        return ['id' => "tpl-{$type}-{$templateKey}", 'type' => $type, 'template' => $templateKey, 'style' => Registry::template($type, $templateKey)['style'] ?? 'card', 'version' => 0, 'priority' => 0] + $config;
    }

    /**
     * A bundle model rendered with sample products, in the storefront payload shape.
     */
    public static function bundlePreview(string $modelKey, array $branding = [], ?string $preset = null): array
    {
        $config = BundleSchema::defaults($modelKey, $branding);
        if ($preset) {
            $config['design'] = array_merge($config['design'], BundleSchema::designDefaults($preset));
        }
        $samples = array_slice(self::samples(), 0, 3);
        foreach ($config['offers'] as $i => $offer) {
            if ($offer['kind'] === 'multi' && $offer['products'] === []) {
                $config['offers'][$i]['products'] = array_slice($samples, 0, 3);
            }
            if ($offer['kind'] === 'mono' && $offer['product'] === []) {
                $config['offers'][$i]['product'] = [$samples[$i % 3]];
            }
            foreach ($offer['gifts'] as $g => $gift) {
                if ($gift['product'] === []) {
                    $config['offers'][$i]['gifts'][$g]['product'] = [self::samples()[3]];
                }
            }
        }
        if ($config['mix']['pool'] === []) {
            $config['mix']['pool'] = $samples;
        }

        return ['id' => "tpl-bundles-{$modelKey}", 'type' => 'bundles', 'template' => $modelKey, 'style' => BundleSchema::model($modelKey)['layout'] ?? 'vertical', 'version' => 0, 'priority' => 0]
            + BundleSchema::payload($config);
    }

    /**
     * A progressive gifts model with sample gifts, in the storefront payload shape.
     */
    public static function giftPreview(string $modelKey): array
    {
        $config = GiftSchema::defaults($modelKey);
        foreach ($config['milestones'] as $i => $m) {
            if (in_array($m['reward'], ['gift', 'choice'], true)) {
                $config['milestones'][$i]['products'] = $m['reward'] === 'choice' ? array_slice(self::samples(), 0, 3) : [self::samples()[3]];
            }
        }

        return ['id' => "tpl-progressive-gifts-{$modelKey}", 'type' => 'progressive-gifts', 'template' => $modelKey, 'style' => GiftSchema::model($modelKey)['layout'] ?? 'classic', 'version' => 0, 'priority' => 0]
            + GiftSchema::payload($config);
    }

    /**
     * Sample products for previews, with illustrated images (resources/experiences/sample-images.json).
     */
    public static function samples(): array
    {
        static $samples;
        if ($samples === null) {
            $img = json_decode(file_get_contents(resource_path('experiences/sample-images.json')), true);
            $samples = [
                ['id' => 'gid://shopify/Product/1', 'title' => 'Glow Serum', 'price' => 29.0, 'compare_at' => 36.0, 'image' => $img['bottle']],
                ['id' => 'gid://shopify/Product/2', 'title' => 'Night Cream', 'price' => 34.0, 'image' => $img['jar']],
                ['id' => 'gid://shopify/Product/3', 'title' => 'Gentle Cleanser', 'price' => 18.0, 'image' => $img['tube']],
            ];
            $samples[] = ['id' => 'gid://shopify/Product/9', 'title' => 'Free gift', 'price' => 12.0, 'image' => $img['gift']];
        }

        return $samples;
    }

    public static function currentVersion(string $type, string $key): ?CroTemplateVersion
    {
        return CroTemplateVersion::whereHas('template', fn ($q) => $q->where('type', $type)->where('key', $key))
            ->orderByDesc('version')
            ->first();
    }
}
