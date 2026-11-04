<?php

namespace App\Experiences;

/**
 * Design for every offer (G37), step 2: text and layout for each storefront offer, with desktop,
 * tablet and mobile values. Saved as design.layout = {desktop|tablet|mobile: {element: {setting: value}}}.
 * Tablet follows desktop and mobile follows tablet until changed; an unset setting keeps today's look.
 *
 * The CSS is scoped to the offer here (the storefront's own scoping can't handle media queries):
 * each rule sits alone in an @media block, which the storefront passes through untouched.
 */
class Layout
{
    public const DEVICES = ['desktop' => 'all', 'tablet' => '(max-width: 989px)', 'mobile' => '(max-width: 749px)'];

    public const FONTS = ['heading' => 'var(--font-heading-family, inherit)', 'body' => 'var(--font-body-family, inherit)', 'system' => 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif'];

    /** setting => [kinds it applies to, rule] */
    private const SETTINGS = [
        'font' => [['text']], 'size' => [['text'], 8, 72], 'weight' => [['text']], 'line' => [['text'], 0.8, 3], 'track' => [['text'], -0.1, 0.5],
        'case' => [['text']], 'align' => [['text']],
        'pad' => [['box', 'grid'], 0, 80], 'gap' => [['box', 'grid'], 0, 60], 'radius' => [['box', 'grid'], 0, 60], 'border' => [['box', 'grid'], 0, 8],
        'above' => [['box', 'grid'], 0, 120], 'below' => [['box', 'grid'], 0, 120], 'cols' => [['grid'], 1, 6],
    ];

    /** type => [element => [label, selector ('' = the whole block), text|box|grid]] */
    public static function all(): array
    {
        $title = ['title' => ['Title', '.oo-title', 'text'], 'subtitle' => ['Subtitle', '.oo-sub', 'text']];
        $block = ['box_block' => ['Whole block', '', 'box']];
        $button = ['button' => ['Button text', '.oo-btn', 'text'], 'box_button' => ['Button', '.oo-btn', 'box']];

        return [
            'countdown' => $title + [
                'eyebrow' => ['Small text above', '.oo-cd-eyebrow', 'text'], 'digits' => ['Digits', '.oo-u b', 'text'], 'units' => ['Unit labels', '.oo-u small', 'text'],
                'message' => ['Message', '.oo-message', 'text'], 'code' => ['Discount code', '.oo-cd-code span', 'text'],
            ] + $block + [
                'box_timer' => ['Timer row', '.oo-timer', 'box'], 'box_digits' => ['Digit boxes', '.oo-cd-boxes .oo-u, .oo-cd-flip .oo-u b', 'box'],
            ] + $button,
            'cart-upsells' => $title + [
                'name' => ['Product name', '.oo-name', 'text'], 'price' => ['Price', '.oo-price', 'text'],
            ] + $block + [
                'box_cards' => ['Product list', '.oo-cards', 'grid'], 'box_card' => ['Product cards', '.oo-card', 'box'],
            ] + $button,
            'sticky-atc' => [
                'name' => ['Product name', '.oo-name', 'text'], 'price' => ['Price', '.oo-price', 'text'],
            ] + $block + ['box_picture' => ['Product picture', '.oo-sa-img', 'box']] + $button,
            'preorder' => ['title' => ['Title', '.oo-title', 'text']] + [
                'badge' => ['Badge', '.oo-po-badge', 'text'], 'date' => ['Ship date', '.oo-po-date', 'text'], 'message' => ['Message', '.oo-po-msg', 'text'], 'note' => ['Note', '.oo-po-note', 'text'],
            ] + $block + ['box_badge' => ['Badge box', '.oo-po-badge', 'box']] + $button,
            'sales-pop' => [
                'who' => ['First line (who bought)', '.oo-sp-who', 'text'], 'what' => ['Product name', '.oo-sp-what', 'text'], 'when' => ['Time', '.oo-sp-meta', 'text'],
                'box_card' => ['Pop-up card', '.oo-sp-card', 'box'], 'box_picture' => ['Product picture', '.oo-sp-img', 'box'],
            ],
            'trust' => ['title' => ['Title', '.oo-title', 'text']] + [
                'badges' => ['Badge text', '.oo-badges li', 'text'], 'guarantee' => ['Guarantee text', '.oo-guarantee p', 'text'],
            ] + $block + ['box_badges' => ['Badge list', '.oo-badges', 'grid']],
        ];
    }

    public static function for(string $type): array
    {
        return self::all()[$type] ?? [];
    }

    /** Saved values, kept to known elements, settings and ranges. */
    public static function clean(string $type, array $input): object
    {
        $elements = self::for($type);
        $out = [];
        foreach (array_keys(self::DEVICES) as $device) {
            foreach ((array) ($input[$device] ?? []) as $key => $values) {
                if (! isset($elements[$key])) {
                    continue;
                }
                $kind = $elements[$key][2];
                foreach ((array) $values as $setting => $value) {
                    $rule = self::SETTINGS[$setting] ?? null;
                    if (! $rule || ! in_array($kind, $rule[0], true) || $value === null || $value === '') {
                        continue;
                    }
                    $clean = match ($setting) {
                        'font' => isset(self::FONTS[$value]) ? $value : null,
                        'weight' => in_array((int) $value, [300, 400, 500, 600, 700, 800, 900], true) ? (int) $value : null,
                        'case' => in_array($value, ['none', 'uppercase', 'lowercase', 'capitalize'], true) ? $value : null,
                        'align' => in_array($value, ['left', 'center', 'right'], true) ? $value : null,
                        default => is_numeric($value) ? round(min($rule[2], max($rule[1], (float) $value)), 3) : null,
                    };
                    if ($clean !== null) {
                        $out[$device][$key][$setting] = $clean;
                    }
                }
            }
        }

        return (object) array_map(fn ($d) => (object) $d, $out);
    }

    /** The declarations for one element's settings. */
    public static function declarations(array $values): string
    {
        $px = fn ($v) => (0 + $v).'px';
        $css = [];
        foreach ($values as $setting => $v) {
            $css[] = match ($setting) {
                'font' => 'font-family:'.(self::FONTS[$v] ?? 'inherit'),
                'size' => 'font-size:'.$px($v), 'weight' => 'font-weight:'.(int) $v, 'line' => 'line-height:'.(0 + $v),
                'track' => 'letter-spacing:'.(0 + $v).'em', 'case' => 'text-transform:'.$v, 'align' => 'text-align:'.$v,
                'pad' => 'padding:'.$px($v), 'gap' => 'gap:'.$px($v), 'radius' => 'border-radius:'.$px($v),
                'border' => (float) $v > 0 ? 'border:'.$px($v).' solid color-mix(in srgb, currentColor 18%, transparent)' : 'border:0',
                'above' => 'margin-top:'.$px($v), 'below' => 'margin-bottom:'.$px($v),
                'cols' => 'display:grid;grid-template-columns:repeat('.(int) $v.', minmax(0, 1fr))',
                default => '',
            };
        }

        return implode(';', array_map(fn ($d) => preg_replace('/(;|$)/', '!important$1', $d), array_filter($css)));
    }

    /** Scoped CSS for an offer: one rule per @media block, desktop first, then tablet, then mobile. */
    public static function css(string $type, array $layout, string $handle): string
    {
        $elements = self::for($type);
        $scope = '[data-oo-exp="'.preg_replace('/[^A-Za-z0-9_-]/', '', $handle).'"]';
        $out = [];
        foreach (self::DEVICES as $device => $media) {
            foreach ((array) ($layout[$device] ?? []) as $key => $values) {
                if (! isset($elements[$key]) || ! ($decl = self::declarations((array) $values))) {
                    continue;
                }
                $selector = $elements[$key][1] === '' ? $scope
                    : implode(', ', array_map(fn ($s) => $scope.' '.trim($s), explode(',', $elements[$key][1])));
                $out[] = "@media {$media}{{$selector}{{$decl}}}";
            }
        }

        return implode("\n", $out);
    }

    /** For the editor. */
    public static function meta(string $type): array
    {
        return array_map(fn ($e) => ['label' => $e[0], 'selector' => $e[1], 'kind' => $e[2]], self::for($type));
    }
}
