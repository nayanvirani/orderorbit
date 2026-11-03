<?php

namespace App\Experiences;

/**
 * Texts and visibility for every offer: the parts a merchant can hide and the fixed words they can
 * change. Saved as content.hide (part keys) and content.words (key => text); bundles and gift
 * campaigns keep them in their "look" section. Storefront parts are hidden with a scoped CSS rule
 * (the part's selector); checkout, Thank You, account and after-payment blocks skip hidden parts
 * when they draw. A word left empty is hidden too.
 */
class Parts
{
    /**
     * type => ['parts' => [key => [label, storefront selector or null]], 'words' => [key => [label, default]]]
     */
    public static function all(): array
    {
        $title = ['title' => ['Title', '.oo-title'], 'subtitle' => ['Subtitle', '.oo-sub']];
        $added = ['added' => ['After adding to the cart', 'Added to your cart.'], 'unavailable' => ['When an item can\'t be added', 'This item is unavailable right now.']];

        return [
            'bundles' => [
                'parts' => [
                    'title' => ['Title above the offers', '.oo-bhead'], 'subtitle' => ['Subtitle', '.oo-bsub'], 'timer' => ['Timer', '.oo-timer'],
                    'ribbons' => ['Ribbons (Most popular)', '.oo-blabel'], 'badges' => ['Discount badges (−20%)', '.oo-bbadge, .oo-mt-name em'],
                    'pictures' => ['Product pictures', '.oo-bthumb, .oo-tpics, .oo-mimg, .oo-ximg'], 'second_line' => ['Line under each title', '.oo-bsubt'],
                    'compare' => ['Crossed-out prices', '.oo-bprice s, .oo-tsale s, .oo-badd s'], 'pack' => ['Products listed in a pack', '.oo-bpack .oo-bitem-name, .oo-bpack .oo-bitem-price'],
                    'variants' => ['Variant pickers', '.oo-bunits, .oo-variant'], 'gifts' => ['Free gift tiles', '.oo-bgifts, .oo-bboxgift'], 'upsells' => ['Add-ons', '.oo-bups'],
                    'summary' => ['Savings line', '.oo-bsum'], 'radios' => ['Radio buttons', '.oo-boffer input[type="radio"]'], 'message' => ['Progress message (boxes)', '.oo-bmsg'],
                ],
                'words' => $added + [
                    'gift_progress' => ['Box gift: still to unlock', 'Add {count} more to unlock your free gift'], 'gift_unlocked' => ['Box gift: unlocked', 'Free gift unlocked'],
                    'mix_add' => ['Mix & match: add button', '+ Add'], 'bundle_full' => ['Mix & match: when full', 'Bundle full'],
                    'box_sold' => ['Box: sold out', 'Sold out'], 'box_full' => ['Box: full', 'Your box is full'], 'box_ready' => ['Box: ready', 'Your box is ready'],
                ],
            ],
            'progressive-gifts' => [
                'parts' => [
                    'title' => ['Title', '.oo-pg-title'], 'message' => ['Progress message', '.oo-pg-msg'], 'bar' => ['Progress bar', '.oo-pg-bar, .oo-pg-track, .oo-pg-radial'],
                    'steps' => ['Reward steps', '.oo-pg-steps, .oo-pg-list, .oo-pg-cards'], 'labels' => ['Reward names', '.oo-pg-lbl'], 'marks' => ['Step icons', '.oo-pg-mark'],
                    'gifts' => ['Gift pictures', '.oo-pg-gift'], 'ends' => ['End date', '.oo-pg-ends'],
                ],
                'words' => $added + ['choose' => ['Heading of the gift choice', 'Choose your gift']],
            ],
            'cart-upsells' => [
                'parts' => $title + ['pictures' => ['Product pictures', '.oo-card .oo-img'], 'names' => ['Product names', '.oo-card .oo-name'], 'prices' => ['Prices', '.oo-card .oo-price'],
                    'compare' => ['Crossed-out prices', '.oo-card .oo-price s'], 'variants' => ['Variant pickers', '.oo-card .oo-variant']],
                'words' => $added,
            ],
            'countdown' => [
                'parts' => ['eyebrow' => ['Small text above', '.oo-cd-eyebrow'], 'title' => ['Title', '.oo-title'], 'subtitle' => ['Subtitle', '.oo-sub'], 'timer' => ['Timer', '.oo-timer'],
                    'units' => ['Unit names (Days, Hours…)', '.oo-u'], 'separators' => ['Separators (:)', '.oo-sep'], 'badge' => ['Badge', '.oo-cd-badge'], 'code' => ['Discount code', '.oo-cd-code'],
                    'button' => ['Button', '.oo-cd-cta'], 'message' => ['Message', '.oo-message']],
                'words' => ['days' => ['Days', 'Days'], 'hours' => ['Hours', 'Hours'], 'minutes' => ['Minutes', 'Min'], 'seconds' => ['Seconds', 'Sec'], 'copied' => ['After copying the code', 'Copied']],
            ],
            'sticky-atc' => [
                'parts' => ['picture' => ['Product picture', '.oo-sa-img'], 'name' => ['Product name', '.oo-name'], 'price' => ['Price', '.oo-price'], 'compare' => ['Crossed-out price', '.oo-price s']],
                'words' => $added,
            ],
            'preorder' => [
                'parts' => ['title' => ['Title', '.oo-title, .oo-po-head'], 'badge' => ['Badge', '.oo-po-badge'], 'date' => ['Ship date', '.oo-po-date'], 'message' => ['Message', '.oo-po-msg'],
                    'note' => ['Note', '.oo-po-note'], 'bar' => ['Progress bar', '.oo-po-bar, .oo-progress'], 'steps' => ['Steps', '.oo-po-steps'], 'tiles' => ['Tiles', '.oo-po-tiles']],
                'words' => [],
            ],
            'sales-pop' => [
                'parts' => ['picture' => ['Product picture', '.oo-sp-img'], 'who' => ['Who bought', '.oo-sp-who'], 'what' => ['What they bought', '.oo-sp-what'],
                    'when' => ['When', '.oo-sp-meta'], 'verified' => ['Verified mark', '.oo-sp-ver'], 'close' => ['Close button', '.oo-sp-x']],
                'words' => [],
            ],
            'trust' => [
                'parts' => ['title' => ['Title', '.oo-title'], 'badges' => ['Badges', '.oo-badges'], 'icons' => ['Badge icons', '.oo-badges svg'], 'guarantee' => ['Guarantee', '.oo-guarantee']],
                'words' => [],
            ],

            // Checkout, Thank You and Order Status blocks (extensions/orderorbit-checkout).
            'checkout-shipping' => ['parts' => ['bar' => ['Progress bar', null]], 'words' => []],
            'checkout-gift' => ['parts' => ['bar' => ['Progress bar', null], 'picture' => ['Gift picture', null]], 'words' => []],
            'checkout-promo' => ['parts' => ['code' => ['Discount code', null], 'button' => ['Apply button', null]], 'words' => []],
            'checkout-trust' => ['parts' => ['badges' => ['Badges', null], 'guarantee' => ['Guarantee', null]], 'words' => []],
            'checkout-upsell' => ['parts' => ['picture' => ['Product picture', null], 'price' => ['Price', null], 'compare' => ['Crossed-out price', null]], 'words' => []],
            'checkout-addon' => ['parts' => ['picture' => ['Product picture', null], 'price' => ['Price', null]], 'words' => []],
            'checkout-image' => ['parts' => ['caption' => ['Caption', null]], 'words' => []],
            'ty-cross-sell' => ['parts' => ['pictures' => ['Product pictures', null], 'prices' => ['Prices', null]], 'words' => []],
            'ty-reorder' => ['parts' => ['pictures' => ['Product pictures', null]], 'words' => []],
            'ty-review' => ['parts' => ['pictures' => ['Product pictures', null]], 'words' => []],
            'ty-referral' => ['parts' => ['code' => ['Referral code', null]], 'words' => []],
            'ty-survey' => ['parts' => [], 'words' => []],
            'ty-discount' => ['parts' => ['code' => ['Discount code', null], 'expiry' => ['Expiry', null]], 'words' => []],
            'ty-message' => ['parts' => [], 'words' => []],
            'ty-image' => ['parts' => ['caption' => ['Caption', null]], 'words' => []],

            // Customer account blocks (extensions/orderorbit-account).
            'account-orders' => ['parts' => ['stats' => ['Order count and total spent', null]], 'words' => []],
            'account-tracking' => ['parts' => ['steps' => ['Shipment steps', null], 'carrier' => ['Carrier and tracking links', null]], 'words' => []],
            'account-reorder' => ['parts' => ['pictures' => ['Product pictures', null]], 'words' => []],
            'account-rewards' => ['parts' => ['bar' => ['Progress bar', null], 'tiers' => ['Reward tiers', null]], 'words' => []],
            'account-reviews' => ['parts' => ['pictures' => ['Product pictures', null]], 'words' => []],
            'account-products' => ['parts' => ['pictures' => ['Product pictures', null]], 'words' => []],
            'account-support' => ['parts' => [], 'words' => []],

            // The one-click offer after payment (extensions/orderorbit-post-purchase).
            'post-purchase' => ['parts' => ['picture' => ['Product picture', null], 'compare' => ['Crossed-out price', null], 'savings' => ['% off after the price', null], 'charged' => ['"Charged now" line', null]],
                'words' => ['off' => ['% off text', '{percent}% off'], 'charged' => ['Charged now line', 'Charged now to your original payment: {amount}']]],
        ];
    }

    public static function for(string $type): array
    {
        return self::all()[$type] ?? ['parts' => [], 'words' => []];
    }

    /** The saved choices, kept to known keys: ['hide' => [...], 'words' => [...]]. */
    public static function clean(string $type, array $input): array
    {
        $def = self::for($type);
        $hide = array_values(array_intersect(array_keys($def['parts']), array_map('strval', (array) ($input['hide'] ?? []))));
        $words = [];
        foreach ($def['words'] as $key => [, $default]) {
            if (array_key_exists($key, (array) ($input['words'] ?? []))) {
                $words[$key] = mb_substr(trim(strip_tags((string) $input['words'][$key])), 0, 80);
            }
        }

        return ['hide' => $hide, 'words' => (object) $words];
    }

    /** Scoped CSS that hides the storefront parts the merchant turned off. */
    public static function css(string $type, array $hide): string
    {
        $parts = self::for($type)['parts'];
        $selectors = array_values(array_filter(array_map(fn ($k) => $parts[$k][1] ?? null, $hide)));

        return $selectors ? implode(', ', $selectors).'{display:none!important}' : '';
    }

    /** For editors: parts and words with labels, and the selectors for live previews. */
    public static function meta(string $type): array
    {
        $def = self::for($type);

        return [
            'parts' => array_map(fn ($p) => ['label' => $p[0], 'selector' => $p[1]], $def['parts']),
            'words' => array_map(fn ($w) => ['label' => $w[0], 'default' => $w[1]], $def['words']),
        ];
    }
}
