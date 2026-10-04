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
// Other live features also read their templates from the app registry.
$app = fn (string $appType, string $type, string $surface, string $feature, string $label) => array_map(
    fn ($tpl) => ['name' => $tpl['name'], 'type' => $type, 'surface' => $surface, 'feature' => $feature, 'label' => $label],
    array_values(\App\Experiences\Registry::templates($appType)),
);
$gifts = array_map(fn ($m) => ['name' => $m['name'], 'type' => 'gift', 'surface' => 'product', 'feature' => 'progressive-gifts', 'label' => 'Progressive Gifts'], array_values(\App\Experiences\GiftSchema::models()));

return array_merge(
    $bundles,
    $gifts,
    $app('cart-upsells', 'upsell', 'cart', 'cart-upsells', 'Cart Upsell'),
    $app('countdown', 'countdown', 'product', 'countdown-timer', 'Countdown'),
    $app('sticky-atc', 'sticky', 'product', 'sticky-add-to-cart', 'Sticky ATC'),
    $app('preorder', 'countdown', 'product', 'preorder', 'Pre-order'),
    $app('sales-pop', 'trust', 'product', 'sales-pop', 'Sales Pop'),
    $app('trust', 'trust', 'product', 'trust-social-proof', 'Trust'),
    $app('checkout-reviews', 'promo', 'checkout', 'checkout', \App\Experiences\Registry::type('checkout-reviews')['label']),
    $app('checkout-countdown', 'promo', 'checkout', 'checkout', \App\Experiences\Registry::type('checkout-countdown')['label']),
    $app('checkout-shipping', 'promo', 'checkout', 'checkout', \App\Experiences\Registry::type('checkout-shipping')['label']),
    $app('checkout-gift', 'promo', 'checkout', 'checkout', \App\Experiences\Registry::type('checkout-gift')['label']),
    $app('checkout-promo', 'promo', 'checkout', 'checkout', \App\Experiences\Registry::type('checkout-promo')['label']),
    $app('checkout-trust', 'promo', 'checkout', 'checkout', \App\Experiences\Registry::type('checkout-trust')['label']),
    $app('ty-cross-sell', 'thankyou', 'thank-you', 'checkout', \App\Experiences\Registry::type('ty-cross-sell')['label']),
    $app('ty-reorder', 'thankyou', 'thank-you', 'checkout', \App\Experiences\Registry::type('ty-reorder')['label']),
    $app('ty-review', 'thankyou', 'thank-you', 'checkout', \App\Experiences\Registry::type('ty-review')['label']),
    $app('ty-referral', 'thankyou', 'thank-you', 'checkout', \App\Experiences\Registry::type('ty-referral')['label']),
    $app('ty-survey', 'thankyou', 'thank-you', 'checkout', \App\Experiences\Registry::type('ty-survey')['label']),
    $app('ty-discount', 'thankyou', 'thank-you', 'checkout', \App\Experiences\Registry::type('ty-discount')['label']),
    $app('ty-message', 'thankyou', 'thank-you', 'checkout', \App\Experiences\Registry::type('ty-message')['label']),
    $t('account', 'account', 'customer-accounts', ['My Orders', 'Track Order', 'Reorder', 'My Rewards', 'My Reviews', 'My Products', 'Support'], 'Customer Account'),
    $t('flow', 'automation', 'automation', ['Review Request', 'Delivery Follow-up', 'New Customer Welcome', 'VIP Customer', 'Reorder Reminder', 'Win-back', 'Cross-sell', 'Product Education', 'Refund Follow-up', 'Cancellation Follow-up'], 'Automation'),
);
