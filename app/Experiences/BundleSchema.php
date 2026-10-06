<?php

namespace App\Experiences;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The Bundles module (MoonBundle-style): bundle types, their ready-made models
 * (templates), and the bundle config: settings, offers, mix & match slots,
 * gifts, upsells, savings summary and design.
 *
 * Pricing: offers of one product (quantity breaks, variant offers) get their
 * saving from the OrderOrbit discount function; offers of several products and
 * mix & match merge into one cart line through the cart transform. Gifts ride
 * along in the same group and are free when the offer qualifies.
 */
class BundleSchema
{
    public const TYPES = [
        'quantity-breaks' => [
            'label' => 'Quantity breaks',
            'lead' => 'Buy more, pay less.',
            'example' => 'Buy one = normal price. Buy two = −20%.',
            'goal' => 'Encourage multiple purchases of the same product.',
            'offer_kinds' => ['quantity'],
        ],
        'quantity-gifts' => [
            'label' => 'Quantity breaks + gifts',
            'lead' => 'Buy more and unlock gifts.',
            'example' => '2 products = 1 gift. 3 products = 2 gifts.',
            'goal' => 'Boost the cart with rewards.',
            'offer_kinds' => ['quantity'],
        ],
        'variant-offers' => [
            'label' => 'Variant offers',
            'lead' => 'Choose a variant and get a better price.',
            'example' => 'Pack of 10 = $15. Pack of 25 = $30 (−20%).',
            'goal' => 'Highlight high-value variants.',
            'offer_kinds' => ['mono'],
        ],
        'mix-match' => [
            'label' => 'Bundle builder & mix and match',
            'lead' => 'Let customers build their own pack.',
            'example' => 'Choose 3 products from 10 = −15%.',
            'goal' => 'Personalise the shopping experience.',
            'offer_kinds' => [],
        ],
        'fixed' => [
            'label' => 'Fixed bundle',
            'lead' => 'Buy a product pack and save.',
            'example' => 'Buy A B C together = −15%.',
            'goal' => 'Increase cart value.',
            'offer_kinds' => ['multi', 'quantity'],
        ],
        'fixed-gifts' => [
            'label' => 'Fixed bundle + gifts',
            'lead' => 'Buy a pack and unlock gifts.',
            'example' => 'A B C = 2 gifts offered.',
            'goal' => 'Increase the perceived value of the bundle.',
            'offer_kinds' => ['multi', 'quantity'],
        ],
    ];

    public const OFFER_KINDS = ['quantity' => 'Quantity break', 'multi' => 'Multi-products', 'mono' => 'Mono-product'];

    public const DISCOUNTS = ['percentage' => '% off', 'amount' => 'Amount off', 'fixed_price' => 'Fixed price', 'none' => 'No discount'];

    public const PRESETS = [
        'black' => ['accent' => '#111111', 'selected_background' => '#f4f4f4', 'button_background' => '#111111', 'label_background' => '#111111'],
        'pink' => ['accent' => '#e0147b', 'selected_background' => '#fdf0f6', 'button_background' => '#e0147b', 'label_background' => '#e0147b'],
        'orange' => ['accent' => '#f06a0f', 'selected_background' => '#fff4ec', 'button_background' => '#f06a0f', 'label_background' => '#f06a0f'],
        'yellow' => ['accent' => '#d9a400', 'selected_background' => '#fffbea', 'button_background' => '#d9a400', 'label_background' => '#d9a400'],
        'green' => ['accent' => '#12915a', 'selected_background' => '#effaf4', 'button_background' => '#12915a', 'label_background' => '#12915a'],
        'blue' => ['accent' => '#2448ff', 'selected_background' => '#eff2ff', 'button_background' => '#2448ff', 'label_background' => '#2448ff'],
        'purple' => ['accent' => '#7b2ff2', 'selected_background' => '#f5efff', 'button_background' => '#7b2ff2', 'label_background' => '#7b2ff2'],
        'red' => ['accent' => '#d92d20', 'selected_background' => '#fff1f0', 'button_background' => '#d92d20', 'label_background' => '#d92d20'],
        'night' => ['accent' => '#8b7bff', 'selected_background' => '#221d4f', 'button_background' => '#8b7bff', 'label_background' => '#8b7bff', 'background' => '#14112f', 'text' => '#ffffff', 'muted' => '#b9b5dc', 'border' => '#2e2a5a', 'gift_background' => '#1d1946', 'summary_background' => '#183a2c', 'summary_text' => '#6ee7b7', 'badge_background' => '#3a1830', 'badge_text' => '#ff8fb3'],
        'luxe' => ['accent' => '#c9a45c', 'selected_background' => '#241f1a', 'button_background' => '#c9a45c', 'button_text' => '#1a1714', 'label_background' => '#c9a45c', 'label_text' => '#1a1714', 'background' => '#1a1714', 'text' => '#f5efe4', 'muted' => '#bfb39f', 'border' => '#3a332a', 'gift_background' => '#241f1a', 'summary_background' => '#2a241c', 'summary_text' => '#e5c886', 'badge_background' => '#3a2f1c', 'badge_text' => '#e5c886'],
    ];

    /** Visual skins: each bundle template has its own look (header, offer cards, selection and button). */
    public const SKINS = ['classic', 'tiles', 'promo', 'spotlight', 'minimal', 'gift', 'ribbon', 'soft', 'cards', 'list', 'night', 'market', 'outline', 'fbt', 'checklist', 'luxe'];

    /**
     * Ready-made models per bundle type. "layout" and "style" pick the storefront
     * rendering; "offers" and the rest override the type defaults.
     */
    public static function models(): array
    {
        return [
            'qb-classic' => ['type' => 'quantity-breaks', 'name' => 'Classic quantity breaks', 'description' => 'Stacked tiers with a “Most popular” ribbon on the middle offer.', 'layout' => 'vertical', 'skin' => 'classic', 'design' => ['preset' => 'black']],
            'qb-horizontal' => ['type' => 'quantity-breaks', 'name' => 'Horizontal tiers', 'description' => 'Tiers side by side with progressive discounts.', 'layout' => 'horizontal', 'skin' => 'tiles', 'design' => ['preset' => 'purple', 'radius' => 16, 'border_width' => 1]],
            'qb-bogo' => ['type' => 'quantity-breaks', 'name' => '1 bought = 1 free', 'description' => 'BOGO-style offers: two for the price of one, four for the price of two.', 'layout' => 'vertical',
                'offers' => [
                    self::quantityOffer(2, 'percentage', 50, '1 bought = 1 free', '2 products for the price of one'),
                    self::quantityOffer(4, 'percentage', 50, '2 bought = 2 free', '4 products for the price of 2', label: 'Most advantageous offer', highlight: true),
                ], 'skin' => 'promo', 'design' => ['preset' => 'red', 'radius' => 6]],
            'qb-inversion' => ['type' => 'quantity-breaks', 'name' => 'Quantity inversion offer', 'description' => 'Leads with the 2-product offer and an extra-discount banner, then the single product.', 'layout' => 'vertical',
                'offers' => [
                    self::quantityOffer(2, 'percentage', 20, '2 Products', 'You save {saving}', label: '20% Additional discount', highlight: true, preselected: true),
                    self::quantityOffer(1, 'none', 0, '1 Product', 'Standard price'),
                ], 'skin' => 'spotlight', 'design' => ['preset' => 'orange', 'radius' => 14]],
            'qb-compact' => ['type' => 'quantity-breaks', 'name' => 'Compact selector', 'description' => 'A slim radio list that fits tight product pages.', 'layout' => 'vertical', 'style' => 'compact', 'skin' => 'minimal', 'design' => ['preset' => 'black', 'radius' => 8, 'border_width' => 1, 'offer_title_size' => 15, 'price_size' => 16]],

            'qg-classic' => ['type' => 'quantity-gifts', 'name' => 'Quantity breaks + gifts', 'description' => 'Each tier unlocks more free gifts, shown under the offers.', 'layout' => 'vertical', 'skin' => 'gift', 'design' => ['preset' => 'pink', 'radius' => 18]],
            'qg-horizontal' => ['type' => 'quantity-gifts', 'name' => 'Gift tiers side by side', 'description' => 'Horizontal tiers with the gifts each one unlocks.', 'layout' => 'horizontal', 'skin' => 'ribbon', 'design' => ['preset' => 'yellow', 'radius' => 12]],

            'vo-classic' => ['type' => 'variant-offers', 'name' => 'Classic variant offers', 'description' => 'One card per pack size or variant, each with its own price.', 'layout' => 'vertical', 'skin' => 'soft', 'design' => ['preset' => 'blue', 'radius' => 22, 'border_width' => 1]],
            'vo-grid' => ['type' => 'variant-offers', 'name' => 'Variant cards', 'description' => 'Variant offers as a grid of cards.', 'layout' => 'grid', 'skin' => 'cards', 'design' => ['preset' => 'green', 'radius' => 16]],

            'mm-bundler' => ['type' => 'mix-match', 'name' => 'Mix & match — bundler', 'description' => 'Product slots in a list with progressive discounts as the bundle fills.', 'layout' => 'vertical', 'skin' => 'list', 'design' => ['preset' => 'purple', 'radius' => 12]],
            'mm-slots' => ['type' => 'mix-match', 'name' => 'Mix & match — slot cards', 'description' => 'A row of slot cards shoppers fill one by one.', 'layout' => 'horizontal', 'skin' => 'night', 'design' => ['preset' => 'night', 'radius' => 14]],
            'mm-grid' => ['type' => 'mix-match', 'name' => 'Mix & match — product grid', 'description' => 'Pick straight from a grid of products with a live bundle total.', 'layout' => 'grid', 'skin' => 'market', 'design' => ['preset' => 'orange', 'radius' => 14]],

            'fx-classic' => ['type' => 'fixed', 'name' => 'Classic fixed bundle', 'description' => 'The single product or the full pack, with the pack’s products listed.', 'layout' => 'vertical', 'skin' => 'outline', 'design' => ['preset' => 'blue', 'radius' => 4, 'selected_background' => '#ffffff']],
            'fx-fbt' => ['type' => 'fixed', 'name' => 'Frequently bought together', 'description' => 'Products side by side with plus signs and one total.', 'layout' => 'horizontal', 'style' => 'fbt', 'skin' => 'fbt', 'design' => ['preset' => 'black', 'radius' => 12]],
            'fx-checklist' => ['type' => 'fixed', 'name' => 'Complete the set', 'description' => 'A checklist of the pack’s products with the saving highlighted.', 'layout' => 'vertical', 'style' => 'checklist', 'skin' => 'checklist', 'design' => ['preset' => 'green', 'radius' => 10]],

            'fg-classic' => ['type' => 'fixed-gifts', 'name' => 'Fixed bundle + gifts', 'description' => 'A pack that unlocks free gifts, shown as gift tiles.', 'layout' => 'vertical', 'skin' => 'luxe', 'design' => ['preset' => 'luxe', 'radius' => 6]],
        ];
    }

    public static function model(string $key): ?array
    {
        return self::models()[$key] ?? null;
    }

    /**
     * Registry templates for the "bundles" experience type.
     */
    public static function templates(): array
    {
        return array_map(fn ($m) => ['name' => $m['name'], 'style' => $m['style'] ?? $m['layout'], 'bundle_type' => $m['type']], self::models());
    }

    public static function quantityOffer(int $quantity, string $discount, float $value, ?string $title = null, ?string $subtitle = null, array $gifts = [], string $label = '', bool $highlight = false, bool $preselected = false): array
    {
        return [
            'id' => 'o'.$quantity.substr(md5($title.$discount.$value), 0, 4),
            'kind' => 'quantity',
            'title' => $title ?? ($quantity === 1 ? '1 Product' : "{$quantity} Products"),
            'subtitle' => $subtitle ?? ($value > 0 ? 'You save {saving}' : 'Standard price'),
            'quantity' => $quantity,
            'product' => [],
            'products' => [],
            'discount_type' => $discount,
            'discount_value' => $value,
            'badge' => '',
            'label' => $label,
            'highlight' => $highlight,
            'preselected' => $preselected,
            'visible' => true,
            'gifts' => $gifts,
        ];
    }

    private static function offer(array $values): array
    {
        return array_merge(self::quantityOffer(1, 'none', 0), $values);
    }

    /**
     * Default offers per bundle type.
     */
    private static function defaultOffers(string $type): array
    {
        return match ($type) {
            'quantity-breaks' => [
                self::quantityOffer(1, 'none', 0),
                self::quantityOffer(2, 'percentage', 10, label: 'Most popular', highlight: true, preselected: true),
                self::quantityOffer(3, 'percentage', 20),
            ],
            'quantity-gifts' => [
                self::quantityOffer(1, 'none', 0),
                self::quantityOffer(2, 'percentage', 10, subtitle: '+1 free gift', gifts: [['product' => [], 'quantity' => 1]], label: 'Most popular', highlight: true, preselected: true),
                self::quantityOffer(3, 'percentage', 15, subtitle: '+2 free gifts', gifts: [['product' => [], 'quantity' => 2]]),
            ],
            'variant-offers' => [
                self::offer(['id' => 'v1', 'kind' => 'mono', 'title' => 'Pack of 1', 'subtitle' => 'Standard price', 'quantity' => 1]),
                self::offer(['id' => 'v2', 'kind' => 'mono', 'title' => 'Pack of 2', 'subtitle' => 'You save {saving}', 'quantity' => 1, 'discount_type' => 'percentage', 'discount_value' => 15, 'label' => 'Best value', 'highlight' => true, 'preselected' => true]),
            ],
            'fixed' => [
                self::quantityOffer(1, 'none', 0, '1 Product', 'Standard price'),
                self::offer(['id' => 'm1', 'kind' => 'multi', 'title' => 'Complete bundle', 'subtitle' => 'You save {saving}', 'discount_type' => 'percentage', 'discount_value' => 15, 'label' => 'Best value', 'highlight' => true, 'preselected' => true]),
            ],
            'fixed-gifts' => [
                self::quantityOffer(1, 'none', 0, '1 Product', 'Standard price'),
                self::offer(['id' => 'm1', 'kind' => 'multi', 'title' => 'Complete bundle', 'subtitle' => '+ free gifts with your pack', 'discount_type' => 'percentage', 'discount_value' => 10, 'label' => 'Most popular', 'highlight' => true, 'preselected' => true, 'gifts' => [['product' => [], 'quantity' => 1]]]),
            ],
            default => [],
        };
    }

    public static function defaults(string $modelKey, array $branding = []): array
    {
        $model = self::model($modelKey) ?? self::model('qb-classic');
        $type = $model['type'];

        $config = [
            'bundle_type' => $type,
            'settings' => [
                'visibility' => 'all',
                'products' => [],
                'collections' => [],
                'excluded' => [],
                'title' => 'BUNDLE & SAVE',
                'subtitle' => match ($type) {
                    'mix-match' => 'Choose '.($model['slots'] ?? 3).' products from our collection',
                    'quantity-gifts', 'fixed-gifts' => 'Free gifts with your order',
                    default => 'The more you buy, the more you save',
                },
                'hide_lines' => false,
                'timer' => ['enabled' => false, 'mode' => 'end_of_day', 'ends_at' => null, 'text' => 'Offer ends in'],
                'layout' => $model['layout'],
                'style' => $model['style'] ?? 'cards',
                'skin' => $model['skin'] ?? 'classic',
                'position' => 'above_atc',
                'after_add' => 'cart',
                'show_variants' => true,
                'hide_theme_form' => true,
                'hide_selectors' => '',
                'button_text' => 'Add to cart',
                'countries' => '',
            ],
            'offers' => self::defaultOffers($type),
            'mix' => [
                'slots' => 3,
                'pool' => [],
                'tiers' => [['count' => 2, 'discount' => 10], ['count' => 3, 'discount' => 15]],
                'slot_text' => 'Choose',
            ],
            'gifts' => ['enabled' => in_array($type, ['quantity-gifts', 'fixed-gifts'], true), 'title' => 'FREE gifts with your order', 'locked_text' => 'Locked'],
            'upsells' => ['enabled' => false, 'title' => 'Complete your order', 'products' => [], 'discount_percent' => 10],
            'summary' => ['enabled' => true, 'text' => 'You save {saving}'],
            'design' => array_merge(self::designDefaults($model['design']['preset'] ?? 'black'), $model['design'] ?? [], array_intersect_key($branding, array_flip(['font']))),
            'schedule' => ['starts_at' => null, 'ends_at' => null],
            'analytics' => ['track_views' => true, 'track_clicks' => true],
            'behavior' => ['priority' => 50],
        ];

        foreach (['offers', 'mix', 'gifts', 'upsells', 'summary'] as $section) {
            if (isset($model[$section])) {
                $config[$section] = $section === 'offers' ? $model[$section] : array_merge($config[$section], $model[$section]);
            }
        }

        return $config;
    }

    public static function designDefaults(string $preset = 'black'): array
    {
        return array_merge([
            'preset' => $preset,
            'accent' => '#111111',
            'button_background' => '#111111',
            'button_text' => '#ffffff',
            'background' => '#ffffff',
            'text' => '#111111',
            'muted' => '#6b6b6b',
            'border' => '#d4d4d4',
            'selected_background' => '#f4f4f4',
            'label_background' => '#111111',
            'label_text' => '#ffffff',
            'badge_background' => '#fde8e8',
            'badge_text' => '#c01818',
            'gift_background' => '#f7f7f7',
            'summary_background' => '#effaf4',
            'summary_text' => '#12704a',
            'radius' => 10,
            'border_width' => 2,
            'spacing' => 'comfortable',
            'title_size' => 15,
            'offer_title_size' => 17,
            'price_size' => 18,
            'font' => 'theme',
            'image_size' => 48,
            'custom_css' => '',
        ], self::PRESETS[$preset] ?? []);
    }

    /**
     * Turns raw editor input into a clean bundle config and field errors
     * ("offers.1.discount_value" style keys).
     *
     * @return array{0: array, 1: array<string, string>}
     */
    public static function normalize(array $input, string $timezone = 'UTC'): array
    {
        $input = self::migrateLegacy($input);
        $errors = [];
        $type = array_key_exists($input['bundle_type'] ?? '', self::TYPES) ? $input['bundle_type'] : 'quantity-breaks';
        $defaults = self::defaults(self::firstModel($type));

        $s = (array) ($input['settings'] ?? []);
        $d = $defaults['settings'];
        $settings = [
            'visibility' => self::pick($s['visibility'] ?? null, ['all', 'products', 'collections'], 'all'),
            'products' => self::resources($s['products'] ?? [], 'Product', 100),
            'collections' => self::resources($s['collections'] ?? [], 'Collection', 50),
            'excluded' => self::resources($s['excluded'] ?? [], 'Product', 100),
            'title' => self::text($s['title'] ?? $d['title'], 80),
            'subtitle' => self::text($s['subtitle'] ?? '', 160),
            'hide_lines' => self::bool($s['hide_lines'] ?? false),
            'timer' => [
                'enabled' => self::bool($s['timer']['enabled'] ?? false),
                'mode' => self::pick($s['timer']['mode'] ?? null, ['end_of_day', 'date'], 'end_of_day'),
                'ends_at' => self::date($s['timer']['ends_at'] ?? null, $timezone),
                'text' => self::text($s['timer']['text'] ?? 'Offer ends in', 60),
            ],
            'layout' => self::pick($s['layout'] ?? null, ['vertical', 'horizontal', 'grid'], $d['layout']),
            'style' => self::pick($s['style'] ?? null, ['cards', 'compact', 'fbt', 'checklist'], 'cards'),
            'skin' => self::pick($s['skin'] ?? null, self::SKINS, 'classic'),
            'position' => self::pick($s['position'] ?? null, ['above_atc', 'below_atc', 'block'], 'above_atc'),
            'after_add' => self::pick($s['after_add'] ?? null, ['cart', 'stay', 'checkout'], 'cart'),
            'show_variants' => self::bool($s['show_variants'] ?? true),
            'hide_theme_form' => self::bool($s['hide_theme_form'] ?? true),
            // Extra CSS selectors to hide for themes with custom product forms.
            'hide_selectors' => mb_substr(preg_replace('/[{}<>;]/', '', (string) ($s['hide_selectors'] ?? '')), 0, 500),
            'button_text' => self::text($s['button_text'] ?? 'Add to cart', 40) ?: 'Add to cart',
            'countries' => strtoupper(preg_replace('/[^A-Za-z, ]/', '', (string) ($s['countries'] ?? ''))),
        ];
        if ($settings['visibility'] === 'products' && $settings['products'] === []) {
            $errors['settings.products'] = 'Choose the products this bundle shows on, or show it on all products.';
        }
        if ($settings['visibility'] === 'collections' && $settings['collections'] === []) {
            $errors['settings.collections'] = 'Choose at least one collection, or show the bundle on all products.';
        }
        if ($settings['timer']['enabled'] && $settings['timer']['mode'] === 'date' && ! $settings['timer']['ends_at']) {
            $errors['settings.timer.ends_at'] = 'Set when the timer ends.';
        }

        // Offers
        $allowed = self::TYPES[$type]['offer_kinds'];
        $offers = [];
        foreach (array_slice(array_values(array_filter((array) ($input['offers'] ?? []), 'is_array')), 0, 8) as $i => $o) {
            $kind = in_array($o['kind'] ?? null, $allowed, true) ? $o['kind'] : ($allowed[0] ?? 'quantity');
            $offer = [
                'id' => preg_replace('/[^a-z0-9]/i', '', (string) ($o['id'] ?? '')) ?: 'o'.$i.substr(md5(uniqid('', true)), 0, 4),
                'kind' => $kind,
                'title' => self::text($o['title'] ?? '', 80),
                'subtitle' => self::text($o['subtitle'] ?? '', 120),
                'quantity' => max(1, min(50, (int) ($o['quantity'] ?? 1))),
                'product' => $kind === 'mono' ? array_slice(self::resources($o['product'] ?? [], 'Product', 1), 0, 1) : [],
                'products' => $kind === 'multi' ? self::resources($o['products'] ?? [], 'Product', 10, quantities: true) : [],
                'discount_type' => self::pick($o['discount_type'] ?? null, array_keys(self::DISCOUNTS), 'none'),
                'discount_value' => max(0, min(100000, round((float) ($o['discount_value'] ?? 0), 2))),
                'badge' => self::text($o['badge'] ?? '', 24),
                'label' => self::text($o['label'] ?? '', 40),
                'highlight' => self::bool($o['highlight'] ?? false),
                'preselected' => self::bool($o['preselected'] ?? false),
                'visible' => self::bool($o['visible'] ?? true),
                'gifts' => [],
            ];
            foreach (array_slice(array_values(array_filter((array) ($o['gifts'] ?? []), 'is_array')), 0, 4) as $g) {
                $offer['gifts'][] = ['product' => array_slice(self::resources($g['product'] ?? [], 'Product', 1), 0, 1), 'quantity' => max(1, min(10, (int) ($g['quantity'] ?? 1)))];
            }
            if ($offer['title'] === '') {
                $errors["offers.{$i}.title"] = 'Give this offer a title.';
            }
            if ($offer['discount_type'] === 'percentage' && $offer['discount_value'] > 100) {
                $errors["offers.{$i}.discount_value"] = 'A percentage can’t be more than 100.';
            }
            if ($kind === 'multi' && count($offer['products']) < 1) {
                $errors["offers.{$i}.products"] = 'Add the products in this pack.';
            }
            if ($kind === 'mono' && $offer['product'] === []) {
                $errors["offers.{$i}.product"] = 'Choose the product (and variant) for this offer.';
            }
            foreach ($offer['gifts'] as $g => $gift) {
                if ($gift['product'] === []) {
                    $errors["offers.{$i}.gifts.{$g}"] = 'Choose the gift product.';
                }
            }
            $offers[] = $offer;
        }
        if ($type !== 'mix-match' && ! collect($offers)->where('visible', true)->count()) {
            $errors['offers'] = 'Add at least one visible offer.';
        }
        // Exactly one offer starts selected.
        if ($offers !== [] && ! collect($offers)->contains('preselected', true)) {
            $offers[0]['preselected'] = true;
        }

        // Mix & match
        $m = (array) ($input['mix'] ?? []);
        $mix = [
            'slots' => max(2, min(8, (int) ($m['slots'] ?? 3))),
            'pool' => self::resources($m['pool'] ?? [], 'Product', 50),
            'tiers' => [],
            'slot_text' => self::text($m['slot_text'] ?? 'Choose', 24) ?: 'Choose',
        ];
        foreach (array_slice(array_values(array_filter((array) ($m['tiers'] ?? []), 'is_array')), 0, 6) as $t) {
            $mix['tiers'][] = ['count' => max(1, min(8, (int) ($t['count'] ?? 1))), 'discount' => max(0, min(100, round((float) ($t['discount'] ?? 0), 2)))];
        }
        usort($mix['tiers'], fn ($a, $b) => $a['count'] <=> $b['count']);
        if ($type === 'mix-match' && count($mix['pool']) < 2) {
            $errors['mix.pool'] = 'Add at least two products shoppers can choose from.';
        }

        $g = (array) ($input['gifts'] ?? []);
        $u = (array) ($input['upsells'] ?? []);
        $sm = (array) ($input['summary'] ?? []);
        $config = [
            'bundle_type' => $type,
            'settings' => $settings,
            'offers' => $offers,
            'mix' => $mix,
            'gifts' => ['enabled' => self::bool($g['enabled'] ?? false), 'title' => self::text($g['title'] ?? '', 80), 'locked_text' => self::text($g['locked_text'] ?? 'Locked', 30)],
            'upsells' => [
                'enabled' => self::bool($u['enabled'] ?? false),
                'title' => self::text($u['title'] ?? '', 80),
                'products' => self::resources($u['products'] ?? [], 'Product', 4),
                'discount_percent' => max(0, min(100, round((float) ($u['discount_percent'] ?? 0), 2))),
            ],
            'summary' => ['enabled' => self::bool($sm['enabled'] ?? true), 'text' => self::text($sm['text'] ?? 'You save {saving}', 80)],
            'design' => self::design((array) ($input['design'] ?? [])),
            'schedule' => [
                'starts_at' => self::date($input['schedule']['starts_at'] ?? null, $timezone),
                'ends_at' => self::date($input['schedule']['ends_at'] ?? null, $timezone),
            ],
            'analytics' => [
                'track_views' => self::bool($input['analytics']['track_views'] ?? true),
                'track_clicks' => self::bool($input['analytics']['track_clicks'] ?? true),
            ],
            'behavior' => ['priority' => max(1, min(100, (int) ($input['behavior']['priority'] ?? 50)))],
        ];
        if ($config['upsells']['enabled'] && $config['upsells']['products'] === []) {
            $errors['upsells.products'] = 'Choose the upsell products, or turn upsells off.';
        }
        if ($config['schedule']['starts_at'] && $config['schedule']['ends_at'] && $config['schedule']['ends_at'] <= $config['schedule']['starts_at']) {
            $errors['schedule.ends_at'] = 'The end must be after the start.';
        }

        return [$config, $errors];
    }

    private static function design(array $in): array
    {
        $defaults = self::designDefaults(array_key_exists($in['preset'] ?? '', self::PRESETS) ? $in['preset'] : 'black');
        $out = [];
        foreach ($defaults as $key => $default) {
            $value = $in[$key] ?? $default;
            $out[$key] = match (true) {
                $key === 'preset' => $defaults['preset'],
                $key === 'custom_css' => mb_substr(strip_tags((string) $value), 0, 6000),
                $key === 'spacing' => self::pick($value, ['compact', 'comfortable', 'spacious'], 'comfortable'),
                $key === 'font' => self::pick($value, ['theme', 'system'], 'theme'),
                is_int($default) => max(0, min(80, (int) $value)),
                default => preg_match('/^#[0-9a-f]{6}$/i', (string) $value) ? strtolower($value) : $default,
            };
        }

        return $out;
    }

    /**
     * Configs saved before the Bundles module (flat "content" with products).
     */
    private static function migrateLegacy(array $input): array
    {
        if (isset($input['bundle_type']) || ! isset($input['content'])) {
            return $input;
        }
        $c = $input['content'];
        $fixed = ($c['bundle_mode'] ?? 'mix') === 'fixed';
        $type = $fixed ? 'fixed' : 'mix-match';
        $migrated = self::defaults($fixed ? 'fx-classic' : 'mm-bundler');
        $migrated['settings']['title'] = $c['headline'] ?? $migrated['settings']['title'];
        $migrated['settings']['subtitle'] = $c['subheadline'] ?? '';
        $migrated['settings']['button_text'] = $c['cta_text'] ?? 'Add to cart';
        $discount = ($c['discount_type'] ?? 'percentage') === 'amount' ? 'amount' : (($c['discount_type'] ?? '') === 'none' ? 'none' : 'percentage');
        if ($fixed) {
            $migrated['offers'][1]['products'] = $c['products'] ?? [];
            $migrated['offers'][1]['discount_type'] = $discount;
            $migrated['offers'][1]['discount_value'] = (float) ($c['discount_value'] ?? 0);
        } else {
            $migrated['mix']['pool'] = $c['products'] ?? [];
            $migrated['mix']['slots'] = max(2, (int) ($c['max_items'] ?? 3));
            $migrated['mix']['tiers'] = [['count' => max(1, (int) ($c['min_items'] ?? 2)), 'discount' => $discount === 'percentage' ? (float) ($c['discount_value'] ?? 0) : 0]];
        }
        $migrated['bundle_type'] = $type;

        return $migrated + ['schedule' => $input['schedule'] ?? [], 'analytics' => $input['analytics'] ?? []];
    }

    public static function firstModel(string $type): string
    {
        foreach (self::models() as $key => $model) {
            if ($model['type'] === $type) {
                return $key;
            }
        }

        return 'qb-classic';
    }

    /**
     * Storefront payload parts for a bundle config.
     *
     * @return array{content: array, design: array, behavior: array, targeting: array}
     */
    public static function payload(array $config): array
    {
        $s = $config['settings'];
        $strip = fn (array $items) => array_map(fn ($p) => array_intersect_key($p, array_flip(['id', 'title', 'handle', 'image', 'price', 'compare_at', 'variant_id', 'variants', 'quantity'])), $items);

        $offers = array_values(array_map(function ($o, $i) use ($strip) {
            $o['index'] = $i;
            $o['product'] = $strip($o['product']);
            $o['products'] = $strip($o['products']);
            $o['gifts'] = array_map(fn ($g) => ['product' => $strip($g['product']), 'quantity' => $g['quantity']], $o['gifts']);

            return $o;
        }, $config['offers'], array_keys($config['offers'])));

        return [
            'content' => [
                'bundle_type' => $config['bundle_type'],
                'settings' => array_diff_key($s, array_flip(['products', 'collections', 'excluded', 'countries'])),
                'offers' => array_values(array_filter($offers, fn ($o) => $o['visible'])),
                'mix' => ['slots' => $config['mix']['slots'], 'pool' => $strip($config['mix']['pool']), 'tiers' => $config['mix']['tiers'], 'slot_text' => $config['mix']['slot_text']],
                'gifts' => $config['gifts'],
                'upsells' => ['enabled' => $config['upsells']['enabled'], 'title' => $config['upsells']['title'], 'products' => $strip($config['upsells']['products']), 'discount_percent' => $config['upsells']['discount_percent']],
                'summary' => $config['summary'],
            ],
            'design' => $config['design'] + [
                // The runtime's shared variables.
                'primary_color' => $config['design']['button_background'],
                'accent_color' => $config['design']['accent'],
                'text_color' => $config['design']['text'],
                'background_color' => $config['design']['background'],
                'radius' => $config['design']['radius'],
                'border' => false,
            ],
            'behavior' => ['priority' => $config['behavior']['priority'], 'after_add' => $s['after_add'], 'position' => $s['position'], 'animation' => 'none', 'dismissible' => false],
            'targeting' => [
                'page_types' => ['product'],
                'products' => $s['visibility'] === 'products' ? $strip($s['products']) : [],
                'collections' => $s['visibility'] === 'collections' ? array_map(fn ($c) => ['id' => $c['id']], $s['collections']) : [],
                'exclude' => array_map(fn ($p) => ['id' => $p['id']], $s['excluded']),
                'countries' => $s['countries'],
                'device' => 'all',
                'customer' => 'all',
            ],
        ];
    }

    // ---------------------------------------------------------------- helpers

    private static function text(mixed $value, int $max): string
    {
        return mb_substr(trim(strip_tags((string) ($value ?? ''))), 0, $max);
    }

    private static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function pick(mixed $value, array $options, string $default): string
    {
        return in_array($value, $options, true) ? $value : $default;
    }

    private static function date(mixed $value, string $timezone): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse((string) $value, $timezone)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private static function resources(mixed $raw, string $kind, int $max, bool $quantities = false): array
    {
        $items = is_string($raw) ? json_decode($raw, true) : $raw;
        $field = ['type' => $kind === 'Product' ? 'products' : 'collections', 'label' => 'Items', 'max_items' => $max, 'quantities' => $quantities];

        return Schema::resourceList($field, is_array($items) ? $items : [], $kind);
    }
}
