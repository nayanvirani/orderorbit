<?php

namespace App\Services\Analytics;

/**
 * The event catalogue: Shopify's standard storefront events (from the web pixel) and Growvia
 * Space's own events. Experience events are recorded under a specific name where the spec has one
 * (growvia:bundle_viewed) and fall back to the generic one (growvia:experience_viewed).
 */
class Events
{
    /** Shopify standard events the pixel records. */
    public const STANDARD = [
        'session_started' => 'Session started',
        'page_viewed' => 'Page viewed',
        'product_viewed' => 'Product viewed',
        'collection_viewed' => 'Collection viewed',
        'search_submitted' => 'Search submitted',
        'product_added_to_cart' => 'Product added to cart',
        'product_removed_from_cart' => 'Product removed from cart',
        'cart_viewed' => 'Cart viewed',
        'checkout_started' => 'Checkout started',
        'payment_info_submitted' => 'Payment info submitted',
        'checkout_completed' => 'Checkout completed (purchase)',
    ];

    public const ORDERORBIT = [
        'growvia:experience_viewed' => 'Widget viewed',
        'growvia:experience_clicked' => 'Widget clicked',
        'growvia:experience_closed' => 'Widget closed',
        'growvia:bundle_viewed' => 'Bundle viewed',
        'growvia:bundle_completed' => 'Bundle added to cart',
        'growvia:shipping_progress_viewed' => 'Shipping progress viewed',
        'growvia:shipping_threshold_reached' => 'Shipping threshold reached',
        'growvia:free_gift_viewed' => 'Free gift viewed',
        'growvia:free_gift_unlocked' => 'Free gift unlocked',
        'growvia:upsell_viewed' => 'Upsell viewed',
        'growvia:upsell_accepted' => 'Upsell accepted',
        'growvia:upsell_declined' => 'Upsell declined',
        'growvia:sticky_atc_viewed' => 'Sticky add to cart viewed',
        'growvia:sticky_atc_clicked' => 'Sticky add to cart clicked',
        'growvia:checkout_block_viewed' => 'Checkout block viewed',
        'growvia:checkout_block_clicked' => 'Checkout block clicked',
        'growvia:added_to_cart' => 'Added to cart from an offer',
        'growvia:reward_unlocked' => 'Reward unlocked',
        'growvia:survey_answered' => 'Survey answered',
        'growvia:experiment_exposed' => 'A/B test exposure',
        'growvia:automation_triggered' => 'Automation triggered',
        'growvia:automation_completed' => 'Automation completed',
    ];

    /** Short codes kept in the "event" column for the offer reports. */
    public const CODES = [
        'experience_viewed' => 'view', 'experience_clicked' => 'click', 'added_to_cart' => 'add',
        'reward_unlocked' => 'unlock', 'upsell_accepted' => 'accept', 'upsell_declined' => 'decline',
        'survey_answered' => 'survey', 'experience_closed' => 'close', 'experiment_exposed' => 'expose',
    ];

    public static function all(): array
    {
        return self::STANDARD + self::ORDERORBIT;
    }

    public static function label(?string $name): string
    {
        return self::all()[$name] ?? (string) $name;
    }

    /** The catalogue name for a Growvia event from an experience of the given type. */
    public static function nameFor(string $event, ?string $type): string
    {
        $type = (string) $type;
        $checkout = str_starts_with($type, 'checkout-') || str_starts_with($type, 'ty-') || $type === 'post-purchase';
        $upsell = in_array($type, ['product-upsells', 'cart-upsells', 'post-purchase'], true);
        $specific = match ($event) {
            'experience_viewed' => match (true) {
                $type === 'bundles' || $type === 'quantity-breaks' => 'bundle_viewed',
                $type === 'shipping-bar' || $type === 'progressive-gifts' => 'shipping_progress_viewed',
                $type === 'free-gifts' => 'free_gift_viewed',
                $type === 'sticky-atc' => 'sticky_atc_viewed',
                $type === 'post-purchase' => 'upsell_viewed',
                $checkout => 'checkout_block_viewed',
                $upsell => 'upsell_viewed',
                default => null,
            },
            'experience_clicked' => match (true) {
                $type === 'sticky-atc' => 'sticky_atc_clicked',
                $checkout => 'checkout_block_clicked',
                default => null,
            },
            'added_to_cart' => $type === 'bundles' || $type === 'quantity-breaks' ? 'bundle_completed' : ($upsell ? 'upsell_accepted' : null),
            'reward_unlocked' => match ($type) {
                'free-gifts', 'checkout-gift' => 'free_gift_unlocked',
                'shipping-bar', 'checkout-shipping' => 'shipping_threshold_reached',
                default => null,
            },
            default => null,
        };

        return 'growvia:'.($specific ?? $event);
    }

    /** Page type from a storefront path. */
    public static function pageType(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $path = '/'.ltrim(preg_replace('#^/[a-z]{2}(-[a-z]{2})?(?=/|$)#i', '', parse_url($path, PHP_URL_PATH) ?: '/'), '/');

        return match (true) {
            $path === '/' => 'index',
            str_contains($path, '/products/') => 'product',
            str_starts_with($path, '/collections') => 'collection',
            str_starts_with($path, '/cart') => 'cart',
            str_starts_with($path, '/search') => 'search',
            str_contains($path, '/checkouts/') || str_starts_with($path, '/checkout') => 'checkout',
            str_starts_with($path, '/account') => 'account',
            str_starts_with($path, '/blogs') => 'blog',
            default => 'page',
        };
    }

    public const PAGE_TYPES = ['index' => 'Home', 'product' => 'Product', 'collection' => 'Collection', 'cart' => 'Cart', 'search' => 'Search', 'checkout' => 'Checkout', 'account' => 'Account', 'blog' => 'Blog', 'page' => 'Other page'];

    /** Breakdown dimensions for the Event Explorer: column => label. */
    public const DIMENSIONS = [
        'experience_handle' => 'Experience', 'template' => 'Template', 'experience_type' => 'Widget type',
        'product_id' => 'Product', 'page_type' => 'Page type', 'device' => 'Device', 'country' => 'Market',
        'source' => 'Traffic source', 'medium' => 'Medium', 'campaign' => 'UTM campaign',
        'experiment_handle' => 'A/B test', 'variant' => 'Variant',
    ];
}
