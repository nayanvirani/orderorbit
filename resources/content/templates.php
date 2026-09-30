<?php

/*
| Public template gallery (/templates) — template names from sections 17–30.
| surface: product, cart, checkout, thank-you, account, automation
| type: the visual family used for the preview
*/

$t = function (string $type, string $surface, string $feature, array $names, string $label) {
    return array_map(fn ($name) => ['name' => $name, 'type' => $type, 'surface' => $surface, 'feature' => $feature, 'label' => $label], $names);
};

// Bundle and progressive gift templates come straight from the app's models, so the site and app always match.
$bundleTypes = \App\Experiences\BundleSchema::TYPES;
$bundles = array_map(fn ($m) => ['name' => $m['name'], 'type' => in_array($m['type'], ['quantity-breaks', 'quantity-gifts', 'variant-offers'], true) ? 'qty' : 'bundle', 'surface' => 'product', 'feature' => 'bundles', 'label' => 'Bundle · '.$bundleTypes[$m['type']]['label']], array_values(\App\Experiences\BundleSchema::models()));
$gifts = array_map(fn ($m) => ['name' => $m['name'], 'type' => 'gift', 'surface' => 'product', 'feature' => 'progressive-gifts', 'label' => 'Progressive Gifts'], array_values(\App\Experiences\GiftSchema::models()));

return array_merge(
    $bundles,
    $gifts,
    $t('upsell', 'cart', 'cart-upsells', ['Carousel', 'Grid', 'Horizontal', 'Minimal Card'], 'Cart Upsell'),
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
