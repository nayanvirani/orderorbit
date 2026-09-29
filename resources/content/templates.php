<?php

/*
| Public template gallery (/templates) — template names from sections 17–30.
| surface: product, cart, checkout, thank-you, account, automation
| type: the visual family used for the preview
*/

$t = function (string $type, string $surface, string $feature, array $names, string $label) {
    return array_map(fn ($name) => ['name' => $name, 'type' => $type, 'surface' => $surface, 'feature' => $feature, 'label' => $label], $names);
};

return array_merge(
    $t('bundle', 'product', 'bundles', ['Premium Bundle', 'Mix & Match', 'Tiered / Buy More Save More', 'Routine Builder', 'Gift Box', 'Visual Product Bundle'], 'Bundle'),
    $t('gift', 'cart', 'free-gift', ['Minimal', 'Progress Card', 'Reward Ladder', 'Premium Gift Card', 'Product Reveal', 'Compact Cart Bar'], 'Free Gift'),
    $t('shipping', 'cart', 'free-shipping-bar', ['Minimal', 'Progress', 'Reward Ladder', 'Premium Card'], 'Shipping Bar'),
    $t('qty', 'product', 'quantity-breaks', ['Tier Cards', 'Radio Selector', 'Horizontal Tiers', 'Premium Pricing Table', 'Compact Selector'], 'Quantity Breaks'),
    $t('upsell', 'product', 'upsell-cross-sell', ['Product Card', 'Side-by-Side', 'Compact', 'Premium Offer'], 'Product Upsell'),
    $t('upsell', 'cart', 'upsell-cross-sell', ['Carousel', 'Grid', 'Horizontal', 'Minimal Card'], 'Cart Upsell'),
    $t('countdown', 'product', 'countdown-timer', ['Minimal', 'Banner', 'Premium Card', 'Offer Countdown', 'Product Countdown'], 'Countdown'),
    $t('sticky', 'product', 'sticky-add-to-cart', ['Simple sticky bar'], 'Sticky ATC'),
    $t('trust', 'product', 'trust-social-proof', ['Review Card', 'Review Slider', 'Rating Strip', 'Customer Quote', 'Avatar Testimonials', 'Trust Row', 'Guarantee Card'], 'Trust'),
    $t('trust', 'checkout', 'checkout', ['Classic Review', 'Modern Card', 'Minimal Slider', 'Premium Testimonial'], 'Checkout Reviews'),
    $t('countdown', 'checkout', 'checkout', ['Compact', 'Banner', 'Card', 'Premium'], 'Checkout Countdown'),
    $t('shipping', 'checkout', 'checkout', ['Single Threshold', 'Multi Threshold', 'Reward Ladder'], 'Checkout Shipping'),
    $t('gift', 'checkout', 'checkout', ['Progress', 'Gift Unlocked', 'Reward Card'], 'Checkout Free Gift'),
    $t('promo', 'checkout', 'checkout', ['Announcement', 'Promotional Card', 'Offer Banner', 'Premium'], 'Checkout Promotion'),
    $t('trust', 'checkout', 'checkout', ['Trust Row', 'Icon Grid', 'Trust Card', 'Guarantee Banner'], 'Checkout Trust'),
    $t('thankyou', 'thank-you', 'checkout', ['Review Request', 'Cross-sell', 'Reorder', 'Referral', 'Survey', 'Next-purchase Discount'], 'Thank You'),
    $t('account', 'account', 'customer-accounts', ['My Orders', 'Track Order', 'Reorder', 'My Rewards', 'My Reviews', 'My Products', 'Support'], 'Customer Account'),
    $t('flow', 'automation', 'automation', ['Review Request', 'Delivery Follow-up', 'New Customer Welcome', 'VIP Customer', 'Reorder Reminder', 'Win-back', 'Cross-sell', 'Product Education', 'Refund Follow-up', 'Cancellation Follow-up'], 'Automation'),
);
