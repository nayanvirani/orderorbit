<?php

namespace App\Experiences;

/**
 * Design for every offer (G37), step 1: a colour for each element of a storefront offer, next to
 * the offer's shared colours (primary, accent, text, background). Saved as design.elements
 * (key => #hex); an element left empty keeps today's look, which comes from the shared colours.
 * Each colour becomes a CSS rule appended to the offer's custom CSS, which the storefront already
 * scopes to the offer's own block, so orderorbit.js stays the same size.
 */
class Elements
{
    private const BTN = '.oo-btn{background:%s!important;border-color:%s!important}';

    private const BTN_TEXT = '.oo-btn{color:%s!important}';

    /** type => [key => [label, css rule with %s for the colour]] */
    public static function all(): array
    {
        $title = ['title' => ['Title', '.oo-title{color:%s!important}'], 'subtitle' => ['Subtitle', '.oo-sub{color:%s!important}']];
        $button = ['button' => ['Button', self::BTN], 'button_text' => ['Button text', self::BTN_TEXT]];

        return [
            'countdown' => [
                'eyebrow' => ['Small text above', '.oo-cd-eyebrow{color:%s!important}'],
            ] + $title + [
                'boxes' => ['Digit boxes', '.oo-cd-boxes .oo-u, .oo-cd-flip .oo-u b{background:%s!important}'],
                'digits' => ['Digits', '.oo-u b{color:%s!important}'],
                'units' => ['Unit labels (Days, Hours…)', '.oo-u small{color:%s!important}'],
                'separators' => ['Separators (:)', '.oo-sep{color:%s!important}'],
                'ring' => ['Progress rings', '.oo-rp{stroke:%s!important}'],
                'badge' => ['Badge', '.oo-cd-badge{background:%s!important;border-color:%s!important}'],
                'code' => ['Discount code chip', '.oo-cd-code{border-color:%s!important}.oo-cd-code em{background:%s!important}'],
                'code_text' => ['Discount code text', '.oo-cd-code span{color:%s!important}'],
                'message' => ['Message', '.oo-message{color:%s!important}'],
            ] + $button,
            'cart-upsells' => $title + [
                'card' => ['Card background', '.oo-card{background:%s!important}'],
                'card_border' => ['Card border', '.oo-card{border:1px solid %s!important}'],
                'name' => ['Product name', '.oo-name{color:%s!important}'],
                'price' => ['Price', '.oo-price{color:%s!important}'],
                'compare' => ['Crossed-out price', '.oo-price s{color:%s!important}'],
            ] + $button,
            'sticky-atc' => [
                'name' => ['Product name', '.oo-name{color:%s!important}'],
                'price' => ['Price', '.oo-price, .oo-price b{color:%s!important}'],
                'compare' => ['Crossed-out price', '.oo-price s{color:%s!important}'],
                'picture' => ['Picture background', '.oo-sa-img{background:%s!important}'],
            ] + $button,
            'preorder' => [
                'badge' => ['Badge', '.oo-po-badge{background:%s!important}'],
                'badge_text' => ['Badge text', '.oo-po-badge{color:%s!important}'],
            ] + ['title' => ['Title', '.oo-title{color:%s!important}']] + [
                'date' => ['Ship date', '.oo-po-date{color:%s!important}'],
                'message' => ['Message', '.oo-po-msg{color:%s!important}'],
                'note' => ['Note', '.oo-po-note{color:%s!important}'],
                'track' => ['Progress track', '.oo-progress{background:%s!important}'],
                'fill' => ['Progress fill', '.oo-progress i{background:%s!important}'],
            ] + $button,
            'sales-pop' => [
                'card' => ['Background', '.oo-sp-card{background:%s!important}'],
                'border' => ['Border', '.oo-sp-card{border:1px solid %s!important}'],
                'who' => ['First line (who bought)', '.oo-sp-who{color:%s!important}'],
                'what' => ['Product name', '.oo-sp-what, .oo-sp-what b{color:%s!important}'],
                'when' => ['Time', '.oo-sp-meta{color:%s!important;opacity:1!important}'],
                'verified' => ['Verified label', '.oo-sp-ver{color:%s!important}'],
                'close' => ['Close icon', '.oo-sp-x{color:%s!important}'],
            ],
            'trust' => ['title' => ['Title', '.oo-title{color:%s!important}']] + [
                'icons' => ['Badge icons', '.oo-badges .oo-icon{color:%s!important}'],
                'badges' => ['Badge text', '.oo-badges li{color:%s!important}'],
                'guarantee_icon' => ['Guarantee icon', '.oo-guarantee .oo-icon{color:%s!important}'],
                'guarantee' => ['Guarantee text', '.oo-guarantee p{color:%s!important}'],
            ],
        ];
    }

    public static function for(string $type): array
    {
        return self::all()[$type] ?? [];
    }

    /** Saved colours, kept to known elements and valid hex colours. */
    public static function clean(string $type, array $input): object
    {
        $out = [];
        foreach (self::for($type) as $key => $_) {
            $v = strtolower(trim((string) ($input[$key] ?? '')));
            if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $v)) {
                $out[$key] = $v;
            }
        }

        return (object) $out;
    }

    /** The CSS for the chosen colours (scoped to the offer by the storefront). */
    public static function css(string $type, array $colours): string
    {
        $rules = [];
        foreach (self::for($type) as $key => [, $rule]) {
            $v = $colours[$key] ?? '';
            if (is_string($v) && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v)) {
                $rules[] = str_replace('%s', $v, $rule);
            }
        }

        return implode("\n", $rules);
    }

    /** For the editor: [key => label]. */
    public static function meta(string $type): array
    {
        return array_map(fn ($e) => ['label' => $e[0], 'rule' => $e[1]], self::for($type));
    }
}
