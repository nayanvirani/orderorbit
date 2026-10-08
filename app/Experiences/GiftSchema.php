<?php

namespace App\Experiences;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The Progressive Gifts module: one progress experience for free shipping, free
 * gifts and order discounts. Milestones unlock by cart value or item count; each
 * reward applies at checkout through the Growvia discount function.
 */
class GiftSchema
{
    public const REWARDS = [
        'gift' => 'Free gift',
        'choice' => 'Choose your gift',
        'shipping' => 'Free shipping',
        'percent' => '% off the order',
        'amount' => 'Amount off the order',
    ];

    public const LAYOUTS = [
        'classic' => 'Classic bar',
        'minimal' => 'Minimal strip',
        'steps' => 'Milestone steps',
        'cards' => 'Reward cards',
        'radial' => 'Radial counter',
    ];

    public static function models(): array
    {
        return [
            'pg-classic' => ['name' => 'Classic bar', 'group' => 'Classic', 'layout' => 'classic', 'description' => 'A clean progress bar with a marker for each reward.', 'design' => ['accent' => '#111111']],
            'pg-minimal' => ['name' => 'Minimal strip', 'group' => 'Classic', 'layout' => 'minimal', 'description' => 'An ultra-compact track that fits anywhere.', 'design' => ['accent' => '#0f8a5f', 'track' => '#d3efe1', 'background' => '#f3fbf7', 'border' => '#cdeedd', 'radius' => 24, 'bar_height' => 6]],
            'pg-steps' => ['name' => 'Milestone steps', 'group' => 'Expressive', 'layout' => 'steps', 'description' => 'Step markers with connecting lines for each reward.', 'design' => ['accent' => '#2448ff', 'track' => '#d9e0ff', 'background' => '#f5f7ff', 'border' => '#dfe5ff', 'radius' => 14]],
            'pg-cards' => ['name' => 'Reward cards', 'group' => 'Expressive', 'layout' => 'cards', 'description' => 'A card for each reward that lights up when it’s unlocked.', 'design' => ['accent' => '#e0147b', 'track' => '#fbd3e7', 'background' => '#fff6fb', 'muted' => '#9b5a7a', 'border' => '#f8d7e8', 'radius' => 18]],
            'pg-radial' => ['name' => 'Radial counter', 'group' => 'Expressive', 'layout' => 'radial', 'description' => 'Circular progress with the reward list beside it.', 'design' => ['accent' => '#ffd166', 'track' => '#2e2a5a', 'background' => '#15123b', 'text' => '#ffffff', 'muted' => '#b9b5dc', 'border' => '#2e2a5a', 'radius' => 18]],
        ];
    }

    public static function model(string $key): ?array
    {
        return self::models()[$key] ?? null;
    }

    public static function templates(): array
    {
        return array_map(fn ($m) => ['name' => $m['name'], 'style' => $m['layout']], self::models());
    }

    public static function milestone(float $threshold, string $reward, string $label, float $value = 0): array
    {
        return ['threshold' => $threshold, 'reward' => $reward, 'label' => $label, 'value' => $value, 'products' => [], 'quantity' => 1];
    }

    public static function defaults(string $modelKey, array $branding = []): array
    {
        $model = self::model($modelKey) ?? self::model('pg-classic');

        return [
            'settings' => [
                'unlock' => 'value',
                'title' => '',
                'progress_message' => 'Add {remaining} more to unlock {reward}!',
                'unlocked_message' => 'You’ve unlocked everything. Enjoy!',
                'layout' => $model['layout'],
                'placement' => 'both',
                'position' => 'below_atc',
                'claim' => 'auto',
                'show_empty' => true,
            ],
            'milestones' => [
                self::milestone(50, 'gift', 'Free gift'),
                self::milestone(75, 'shipping', 'Free shipping'),
                self::milestone(100, 'choice', 'Choose your gift'),
            ],
            'design' => array_merge(self::designDefaults(), $model['design'] ?? []),
            'schedule' => ['starts_at' => null, 'ends_at' => null],
            'analytics' => ['track_views' => true, 'track_clicks' => true],
            'behavior' => ['priority' => 50],
        ];
    }

    public static function designDefaults(): array
    {
        return [
            'accent' => '#111111',
            'track' => '#e7e7e7',
            'background' => '#ffffff',
            'text' => '#111111',
            'muted' => '#8a8a8a',
            'border' => '#e3e3e3',
            'radius' => 10,
            'bar_height' => 8,
            'title_size' => 16,
            'custom_css' => '',
        ];
    }

    /**
     * @return array{0: array, 1: array<string, string>}
     */
    public static function normalize(array $input, string $timezone = 'UTC'): array
    {
        $input = self::migrateLegacy($input);
        $errors = [];
        $d = self::defaults('pg-classic');
        $s = (array) ($input['settings'] ?? []);
        $text = fn ($v, $max) => mb_substr(trim(strip_tags((string) ($v ?? ''))), 0, $max);
        $pick = fn ($v, array $options, $default) => in_array($v, $options, true) ? $v : $default;

        $settings = [
            'unlock' => $pick($s['unlock'] ?? null, ['value', 'count'], 'value'),
            'title' => $text($s['title'] ?? '', 80),
            'progress_message' => $text($s['progress_message'] ?? $d['settings']['progress_message'], 160),
            'unlocked_message' => $text($s['unlocked_message'] ?? $d['settings']['unlocked_message'], 160),
            'layout' => $pick($s['layout'] ?? null, array_keys(self::LAYOUTS), 'classic'),
            'placement' => $pick($s['placement'] ?? null, ['product', 'cart', 'both'], 'both'),
            'position' => $pick($s['position'] ?? null, ['above_atc', 'below_atc', 'block'], 'below_atc'),
            'claim' => $pick($s['claim'] ?? null, ['auto', 'claim'], 'auto'),
            'show_empty' => filter_var($s['show_empty'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        $milestones = [];
        foreach (array_slice(array_values(array_filter((array) ($input['milestones'] ?? []), 'is_array')), 0, 5) as $i => $m) {
            $reward = $pick($m['reward'] ?? null, array_keys(self::REWARDS), 'gift');
            $milestone = [
                'threshold' => round(max(0, (float) ($m['threshold'] ?? 0)), 2),
                'reward' => $reward,
                'label' => $text($m['label'] ?? '', 40) ?: self::REWARDS[$reward],
                'value' => round(max(0, (float) ($m['value'] ?? 0)), 2),
                'products' => in_array($reward, ['gift', 'choice'], true)
                    ? Schema::resourceList(['type' => 'products', 'label' => 'Gifts', 'max_items' => $reward === 'gift' ? 3 : 8], is_array($m['products'] ?? null) ? $m['products'] : (json_decode((string) ($m['products'] ?? ''), true) ?: []), 'Product')
                    : [],
                'quantity' => max(1, min(5, (int) ($m['quantity'] ?? 1))),
            ];
            if ($milestone['threshold'] <= 0) {
                $errors["milestones.{$i}.threshold"] = $settings['unlock'] === 'count' ? 'Set how many items unlock this reward.' : 'Set the cart value that unlocks this reward.';
            }
            if (in_array($reward, ['gift', 'choice'], true) && $milestone['products'] === []) {
                $errors["milestones.{$i}.products"] = $reward === 'choice' ? 'Choose the gifts shoppers can pick from.' : 'Choose the gift product.';
            }
            if (in_array($reward, ['percent', 'amount'], true) && $milestone['value'] <= 0) {
                $errors["milestones.{$i}.value"] = 'Set the discount.';
            }
            if ($reward === 'percent' && $milestone['value'] > 100) {
                $errors["milestones.{$i}.value"] = 'A percentage can’t be more than 100.';
            }
            $milestones[] = $milestone;
        }
        usort($milestones, fn ($a, $b) => $a['threshold'] <=> $b['threshold']);
        if ($milestones === []) {
            $errors['milestones'] = 'Add at least one reward.';
        }

        $design = [];
        $in = (array) ($input['design'] ?? []);
        foreach (self::designDefaults() as $key => $default) {
            $value = $in[$key] ?? $default;
            $design[$key] = match (true) {
                $key === 'custom_css' => mb_substr(strip_tags((string) $value), 0, 6000),
                is_int($default) => max(0, min(60, (int) $value)),
                default => preg_match('/^#[0-9a-f]{6}$/i', (string) $value) ? strtolower($value) : $default,
            };
        }

        $date = function ($v) use ($timezone) {
            try {
                return $v ? CarbonImmutable::parse((string) $v, $timezone)->toIso8601String() : null;
            } catch (Throwable) {
                return null;
            }
        };

        return [[
            'settings' => $settings,
            'milestones' => $milestones,
            'design' => $design,
            'schedule' => ['starts_at' => $date($input['schedule']['starts_at'] ?? null), 'ends_at' => $date($input['schedule']['ends_at'] ?? null)],
            'analytics' => ['track_views' => filter_var($input['analytics']['track_views'] ?? true, FILTER_VALIDATE_BOOLEAN), 'track_clicks' => filter_var($input['analytics']['track_clicks'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'behavior' => ['priority' => max(1, min(100, (int) ($input['behavior']['priority'] ?? 50)))],
        ], $errors];
    }

    /**
     * Shipping bar / free gift configs from before the module.
     */
    private static function migrateLegacy(array $input): array
    {
        if (isset($input['milestones']) || ! isset($input['content'])) {
            return $input;
        }
        $c = $input['content'];
        $config = self::defaults('pg-classic');
        $config['milestones'] = [];
        foreach ($c['thresholds'] ?? [] as $i => $t) {
            $gift = ! empty($c['gift_products']);
            $config['milestones'][] = self::milestone((float) ($t['amount'] ?? 0), $gift ? 'gift' : 'shipping', (string) ($t['reward'] ?? '')) + ($gift ? ['products' => $c['gift_products']] : []);
        }
        if (! empty($c['progress_message'])) {
            $config['settings']['progress_message'] = str_replace('{reward}', '{reward}', $c['progress_message']);
        }

        return $config + ['schedule' => $input['schedule'] ?? []];
    }

    /**
     * @return array{content: array, design: array, behavior: array, targeting: array}
     */
    public static function payload(array $config): array
    {
        $strip = fn (array $items) => array_map(fn ($p) => array_intersect_key($p, array_flip(['id', 'title', 'handle', 'image', 'price', 'variant_id', 'variants'])), $items);
        $s = $config['settings'];

        return [
            'content' => [
                'settings' => $s,
                'milestones' => array_map(fn ($m, $i) => ['index' => $i] + array_merge($m, ['products' => $strip($m['products'])]), $config['milestones'], array_keys($config['milestones'])),
            ],
            'design' => $config['design'] + [
                'primary_color' => $config['design']['accent'],
                'accent_color' => $config['design']['accent'],
                'text_color' => $config['design']['text'],
                'background_color' => $config['design']['background'],
                'radius' => $config['design']['radius'],
                'border' => true,
            ],
            'behavior' => ['priority' => $config['behavior']['priority'], 'position' => $s['placement'] === 'cart' ? 'block' : $s['position'], 'after_add' => 'stay', 'animation' => 'none'],
            'targeting' => ['page_types' => match ($s['placement']) {
                'product' => ['product'],
                'cart' => ['cart'],
                default => ['product', 'cart'],
            }],
        ];
    }
}
