<?php

namespace App\Experiences;

/**
 * Shopify App Store requirement 1.1.4 ("use only factual information"): nothing Growvia shows
 * shoppers may be made up by the merchant. Reviews, ratings and review counts can't be typed in,
 * pre-order "reserved" counts can't be typed in, and timers only count down to a real date or a
 * real daily cutoff (no timers that start per visitor or start again, no "cart reserved" claims).
 *
 * Applied when publishing, so blocks saved before these options were removed stop showing too.
 */
class Factual
{
    /** Types that only showed merchant-typed reviews. */
    public const RETIRED_TYPES = ['checkout-reviews', 'checkout-countdown'];

    /** Trust layouts that were made of typed reviews and ratings. */
    public const REVIEW_TEMPLATES = ['review-card', 'review-slider', 'rating-strip', 'customer-quote', 'avatar-testimonials'];

    /** Whether a published block may show at all. */
    public static function allowed(string $type, ?string $template, array $config): bool
    {
        $content = $config['content'] ?? [];

        return match (true) {
            in_array($type, self::RETIRED_TYPES, true) => false,
            $type === 'trust' => ! in_array($template, self::REVIEW_TEMPLATES, true),
            in_array($type, ['countdown', 'checkout-countdown'], true) => $template !== 'cart-reservation'
                && ! in_array($content['mode'] ?? 'date', ['hours', 'minutes'], true),
            default => true,
        };
    }

    /** The block's content with anything typed-in that would claim made-up numbers removed. */
    public static function clean(string $type, array $config): array
    {
        if ($type === 'trust') {
            unset($config['content']['rating'], $config['content']['review_count'], $config['content']['reviews']);
        }
        if ($type === 'preorder') {
            if (($config['content']['progress'] ?? null) === 'goal') {
                $config['content']['progress'] = 'time';
            }
            unset($config['content']['goal_target'], $config['content']['goal_current'], $config['content']['goal_label']);
        }

        return $config;
    }
}
