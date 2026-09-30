<?php

namespace App\Experiences;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The global experience builder schema (section 16).
 *
 * A config has six sections: content (type-specific), design, behavior,
 * targeting, schedule and analytics. normalize() turns raw form input into a
 * clean config and a list of field errors keyed "section.field[.row.sub]".
 */
class Schema
{
    public const SECTIONS = ['content', 'design', 'behavior', 'targeting', 'schedule', 'analytics'];

    /** Types whose buttons add items to the cart. */
    public const CART_TYPES = ['bundles', 'quantity-breaks', 'bogo', 'product-upsells', 'cart-upsells', 'free-gifts', 'sticky-atc'];

    public const PAGE_TYPES = ['index' => 'Home', 'product' => 'Product pages', 'collection' => 'Collection pages', 'cart' => 'Cart page', 'search' => 'Search', 'page' => 'Other pages'];

    /**
     * Shared fields. Design defaults are replaced by the store's branding.
     */
    public static function shared(): array
    {
        return [
            'design' => [
                'primary_color' => ['type' => 'color', 'label' => 'Primary colour', 'default' => '#303030'],
                'accent_color' => ['type' => 'color', 'label' => 'Accent colour', 'default' => '#5b4bff'],
                'text_color' => ['type' => 'color', 'label' => 'Text colour', 'default' => '#1d1b33'],
                'background_color' => ['type' => 'color', 'label' => 'Background', 'default' => '#ffffff'],
                'button_style' => ['type' => 'select', 'label' => 'Button style', 'default' => 'filled', 'options' => ['filled' => 'Filled', 'outline' => 'Outline']],
                'radius' => ['type' => 'number', 'label' => 'Corner radius (px)', 'default' => 12, 'min' => 0, 'max' => 32],
                'border' => ['type' => 'toggle', 'label' => 'Show border', 'default' => true],
                'spacing' => ['type' => 'select', 'label' => 'Spacing', 'default' => 'comfortable', 'options' => ['compact' => 'Compact', 'comfortable' => 'Comfortable', 'spacious' => 'Spacious']],
                'font' => ['type' => 'select', 'label' => 'Font', 'default' => 'theme', 'options' => ['theme' => 'Match my theme', 'system' => 'System font']],
                'hide_on_mobile' => ['type' => 'toggle', 'label' => 'Hide on mobile', 'default' => false],
                'hide_on_desktop' => ['type' => 'toggle', 'label' => 'Hide on desktop', 'default' => false],
                'custom_css' => ['type' => 'textarea', 'label' => 'Custom CSS', 'default' => '', 'max' => 4000, 'help' => 'Scoped to this experience. Advanced.'],
            ],
            'behavior' => [
                'animation' => ['type' => 'select', 'label' => 'Entrance animation', 'default' => 'fade', 'options' => ['none' => 'None', 'fade' => 'Fade in', 'slide' => 'Slide up']],
                'dismissible' => ['type' => 'toggle', 'label' => 'Shoppers can dismiss it', 'default' => false],
                'after_add' => ['type' => 'select', 'label' => 'After adding to cart', 'default' => 'cart', 'types' => self::CART_TYPES,
                    'options' => ['cart' => 'Go to the cart', 'stay' => 'Stay on the page', 'checkout' => 'Go to checkout']],
                'priority' => ['type' => 'number', 'label' => 'Priority', 'default' => 50, 'min' => 1, 'max' => 100, 'help' => 'When several experiences match the same block, the highest priority shows.'],
            ],
            'targeting' => [
                'page_types' => ['type' => 'checkboxes', 'label' => 'Show on', 'default' => [], 'options' => self::PAGE_TYPES, 'help' => 'Leave empty to show wherever the block is placed.'],
                'products' => ['type' => 'products', 'label' => 'Only these products', 'max_items' => 50],
                'collections' => ['type' => 'collections', 'label' => 'Only products in these collections', 'max_items' => 20],
                'cart_min' => ['type' => 'money', 'label' => 'Cart value at least', 'min' => 0],
                'cart_max' => ['type' => 'money', 'label' => 'Cart value at most', 'min' => 0],
                'device' => ['type' => 'select', 'label' => 'Device', 'default' => 'all', 'options' => ['all' => 'All devices', 'mobile' => 'Mobile only', 'desktop' => 'Desktop only']],
                'customer' => ['type' => 'select', 'label' => 'Shoppers', 'default' => 'all', 'options' => ['all' => 'Everyone', 'new' => 'New shoppers', 'returning' => 'Returning customers']],
                'countries' => ['type' => 'text', 'label' => 'Countries', 'default' => '', 'max' => 400, 'help' => 'Two-letter codes separated by commas, e.g. US, CA. Leave empty for all.'],
                'utm_source' => ['type' => 'text', 'label' => 'UTM source', 'default' => '', 'max' => 100],
                'utm_campaign' => ['type' => 'text', 'label' => 'UTM campaign', 'default' => '', 'max' => 100],
            ],
            'schedule' => [
                'starts_at' => ['type' => 'datetime', 'label' => 'Start'],
                'ends_at' => ['type' => 'datetime', 'label' => 'End'],
            ],
            'analytics' => [
                'track_views' => ['type' => 'toggle', 'label' => 'Track views', 'default' => true],
                'track_clicks' => ['type' => 'toggle', 'label' => 'Track clicks and conversions', 'default' => true],
            ],
        ];
    }

    private static function variants(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $clean = [];
        foreach (array_slice($raw, 0, 100) as $variant) {
            if (is_array($variant) && preg_match('#^gid://shopify/ProductVariant/\d+$#', (string) ($variant['id'] ?? ''))) {
                $clean[] = array_filter([
                    'id' => $variant['id'],
                    'title' => mb_substr(strip_tags((string) ($variant['title'] ?? '')), 0, 120),
                    'price' => isset($variant['price']) && is_numeric($variant['price']) ? round((float) $variant['price'], 2) : null,
                ], fn ($v) => $v !== null && $v !== '');
            }
        }

        return $clean ?: null;
    }

    /**
     * All fields for a type, grouped by section.
     */
    public static function fields(string $type): array
    {
        if ($type === 'bundles' || $type === 'progressive-gifts') {
            // Modules with their own editor and schema (BundleSchema, GiftSchema).
            return ['content' => []];
        }

        // Shared fields marked with "types" only apply to those types.
        $shared = array_map(fn ($fields) => array_filter($fields, fn ($f) => ! isset($f['types']) || in_array($type, $f['types'], true)), self::shared());

        return ['content' => Registry::type($type)['content']] + $shared;
    }

    /**
     * A complete default config for a type, using the store's branding for design.
     */
    public static function defaults(string $type, array $branding = []): array
    {
        if ($type === 'bundles') {
            return BundleSchema::defaults(BundleSchema::firstModel('quantity-breaks'), $branding);
        }
        if ($type === 'progressive-gifts') {
            return GiftSchema::defaults('pg-classic', $branding);
        }

        $config = [];
        foreach (self::fields($type) as $section => $fields) {
            foreach ($fields as $key => $field) {
                $config[$section][$key] = $field['default'] ?? self::empty($field);
            }
        }

        foreach (['primary_color', 'accent_color', 'text_color', 'background_color', 'button_style', 'radius', 'font'] as $key) {
            if (isset($branding[$key])) {
                $config['design'][$key] = $branding[$key];
            }
        }

        return $config;
    }

    /**
     * @return array{0: array, 1: array<string, string>} [config, errors]
     */
    public static function normalize(string $type, array $input, string $timezone = 'UTC'): array
    {
        if ($type === 'bundles') {
            return BundleSchema::normalize($input, $timezone);
        }
        if ($type === 'progressive-gifts') {
            return GiftSchema::normalize($input, $timezone);
        }

        $config = [];
        $errors = [];

        foreach (self::fields($type) as $section => $fields) {
            foreach ($fields as $key => $field) {
                [$value, $error] = self::field($field, $input[$section][$key] ?? null, $timezone);
                $config[$section][$key] = $value;
                if ($error !== null) {
                    $errors["{$section}.{$key}"] = $error;
                } elseif (is_array($value) && ($field['type'] === 'list')) {
                    foreach ($value as $i => $row) {
                        $config[$section][$key][$i] = [];
                        foreach ($field['fields'] as $sub => $subField) {
                            [$v, $e] = self::field($subField + ['required' => in_array($subField['type'], ['number', 'money'], true)], $row[$sub] ?? null, $timezone);
                            $config[$section][$key][$i][$sub] = $v;
                            if ($e !== null) {
                                $errors["{$section}.{$key}.{$i}.{$sub}"] = $e;
                            }
                        }
                    }
                }
            }
        }

        $errors += self::crossFieldErrors($type, $config);

        return [$config, $errors];
    }

    /**
     * @return array{0: mixed, 1: ?string}
     */
    private static function field(array $field, mixed $raw, string $timezone): array
    {
        $required = $field['required'] ?? false;
        $label = $field['label'] ?? 'This field';

        switch ($field['type']) {
            case 'text':
            case 'textarea':
                $value = trim(strip_tags((string) ($raw ?? '')));
                $max = $field['max'] ?? 255;
                if ($required && $value === '') {
                    return [$value, "{$label} is required."];
                }

                return mb_strlen($value) > $max ? [mb_substr($value, 0, $max), "{$label} must be {$max} characters or fewer."] : [$value, null];

            case 'number':
            case 'money':
                if ($raw === null || $raw === '') {
                    return [null, $required ? "{$label} is required." : null];
                }
                if (! is_numeric($raw)) {
                    return [null, "{$label} must be a number."];
                }
                $value = $field['type'] === 'money' || isset($field['step']) || str_contains((string) $raw, '.') ? round((float) $raw, 2) : (int) $raw;
                if (isset($field['min']) && $value < $field['min']) {
                    return [$value, "{$label} must be at least {$field['min']}."];
                }
                if (isset($field['max']) && $value > $field['max']) {
                    return [$value, "{$label} must be {$field['max']} or less."];
                }

                return [$value, null];

            case 'select':
                $value = (string) ($raw ?? $field['default'] ?? '');

                return array_key_exists($value, $field['options']) ? [$value, null] : [$field['default'] ?? array_key_first($field['options']), "Choose a valid {$label}."];

            case 'checkboxes':
                $values = array_values(array_intersect(array_map('strval', (array) ($raw ?? [])), array_keys($field['options'])));

                return [$values, null];

            case 'toggle':
                return [filter_var($raw, FILTER_VALIDATE_BOOLEAN), null];

            case 'color':
                $value = strtolower(trim((string) $raw));

                return preg_match('/^#[0-9a-f]{6}$/', $value) ? [$value, null] : [$field['default'] ?? '#000000', "{$label} must be a hex colour like #5b4bff."];

            case 'datetime':
                if ($raw === null || $raw === '') {
                    return [null, $required ? "{$label} is required." : null];
                }
                try {
                    return [CarbonImmutable::parse((string) $raw, $timezone)->toIso8601String(), null];
                } catch (Throwable) {
                    return [null, "{$label} must be a valid date and time."];
                }

            case 'products':
            case 'collections':
                return self::resources($field, $raw, $field['type'] === 'products' ? 'Product' : 'Collection');

            case 'list':
                $rows = is_string($raw) ? json_decode($raw, true) : $raw;
                $rows = array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
                if ($required && $rows === []) {
                    return [[], "Add at least one row to {$label}."];
                }
                if (count($rows) > ($field['max_items'] ?? 20)) {
                    return [array_slice($rows, 0, $field['max_items']), "{$label} can have at most {$field['max_items']} rows."];
                }

                return [$rows, null];
        }

        return [null, null];
    }

    /**
     * Product/collection picks are stored as small snapshots for rendering.
     */
    /**
     * A cleaned product or collection list (used by BundleSchema), trimmed to the field's maximum.
     */
    public static function resourceList(array $field, array $items, string $kind): array
    {
        return array_slice(self::resources($field, $items, $kind)[0], 0, $field['max_items'] ?? 50);
    }

    private static function resources(array $field, mixed $raw, string $kind): array
    {
        $items = is_string($raw) ? json_decode($raw, true) : $raw;
        $items = is_array($items) ? $items : [];

        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! preg_match("#^gid://shopify/{$kind}/\\d+$#", (string) ($item['id'] ?? ''))) {
                continue;
            }
            $clean[] = array_filter([
                'id' => $item['id'],
                'title' => mb_substr(strip_tags((string) ($item['title'] ?? '')), 0, 255),
                'handle' => preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($item['handle'] ?? ''))),
                'image' => filter_var($item['image'] ?? null, FILTER_VALIDATE_URL) ?: null,
                'price' => isset($item['price']) && is_numeric($item['price']) ? round((float) $item['price'], 2) : null,
                'compare_at' => isset($item['compare_at']) && is_numeric($item['compare_at']) ? round((float) $item['compare_at'], 2) : null,
                'variant_id' => preg_match('#^gid://shopify/ProductVariant/\d+$#', (string) ($item['variant_id'] ?? '')) ? $item['variant_id'] : null,
                // Variants the merchant mapped for this product (only when it has options).
                'variants' => self::variants($item['variants'] ?? null),
                'quantity' => ($field['quantities'] ?? false) && isset($item['quantity']) ? max(1, min(20, (int) $item['quantity'])) : null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        $label = $field['label'] ?? 'Products';
        if (($field['required'] ?? false) && $clean === []) {
            return [[], "Choose at least one item for {$label}."];
        }
        if (count($clean) > ($field['max_items'] ?? 50)) {
            return [array_slice($clean, 0, $field['max_items']), "{$label} can have at most {$field['max_items']} items."];
        }

        return [$clean, null];
    }

    private static function crossFieldErrors(string $type, array $config): array
    {
        $errors = [];
        $c = $config['content'];

        if ($type === 'bundles' && isset($c['min_items'], $c['max_items']) && $c['min_items'] > $c['max_items']) {
            $errors['content.max_items'] = 'Maximum selections must be at least the minimum.';
        }
        if (in_array($type, ['free-gifts', 'shipping-bar'], true)) {
            $amounts = array_column($c['thresholds'] ?? [], 'amount');
            $sorted = $amounts;
            sort($sorted);
            if ($amounts !== $sorted || count($amounts) !== count(array_unique($amounts))) {
                $errors['content.thresholds'] = 'Thresholds must go from lowest to highest, with no duplicates.';
            }
        }
        if ($type === 'quantity-breaks' && ($c['default_tier'] ?? 1) > count($c['tiers'] ?? [])) {
            $errors['content.default_tier'] = 'Selected tier must be one of your tiers.';
        }
        if ($type === 'countdown' && ($c['mode'] ?? 'date') === 'date' && empty($c['ends_at'])) {
            $errors['content.ends_at'] = 'Set when the campaign ends.';
        }
        if ($type === 'countdown' && ($c['mode'] ?? 'date') === 'daily' && ! preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', (string) ($c['daily_time'] ?? ''))) {
            $errors['content.daily_time'] = 'Use a 24-hour time like 14:00.';
        }
        if ($type === 'countdown' && ($c['mode'] ?? 'date') === 'date' && ! empty($c['ends_at']) && CarbonImmutable::parse($c['ends_at'])->isPast()) {
            $errors['content.ends_at'] = 'Campaign end must be in the future.';
        }

        $t = $config['targeting'];
        if ($t['cart_min'] !== null && $t['cart_max'] !== null && $t['cart_min'] > $t['cart_max']) {
            $errors['targeting.cart_max'] = 'Maximum cart value must be at least the minimum.';
        }
        if ($t['countries'] !== '') {
            $codes = array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', $t['countries'])));
            if (array_filter($codes, fn ($code) => ! preg_match('/^[A-Z]{2}$/', $code))) {
                $errors['targeting.countries'] = 'Use two-letter country codes separated by commas, e.g. US, CA.';
            }
        }

        $s = $config['schedule'];
        if ($s['starts_at'] && $s['ends_at'] && $s['starts_at'] >= $s['ends_at']) {
            $errors['schedule.ends_at'] = 'End must be after the start.';
        }

        return $errors;
    }

    private static function empty(array $field): mixed
    {
        return match ($field['type']) {
            'products', 'collections', 'list', 'checkboxes' => [],
            'toggle' => false,
            'number', 'money', 'datetime' => null,
            default => '',
        };
    }
}
