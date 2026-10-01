<?php

/*
|--------------------------------------------------------------------------
| CRO experience types (sections 16–25)
|--------------------------------------------------------------------------
|
| Each type lists its templates (names from the MVP scope) and the
| type-specific content fields. Design, behavior, targeting, schedule and
| analytics fields are shared by every type (App\Experiences\Schema).
|
| Field types: text, textarea, number, money, select, toggle, color,
| datetime, products, collections, list (rows of sub-fields).
|
| Template "style" picks the renderer variant: minimal, card, banner,
| premium, compact, ladder, grid, carousel, slider, table, row.
|
| Templates may preset content ("content" => [...]) on top of the type defaults.
|
| "discount" types apply real savings at checkout: bundles merge into one
| cart line through the OrderOrbit cart transform (BundleSync); the others use
| the OrderOrbit discount function, one Shopify automatic discount per
| experience (OfferSync).
|
*/

return [

    // The Bundles module: types, models and settings live in App\Experiences\BundleSchema.
    'bundles' => [
        'label' => 'Bundles',
        'discount' => true,
        'singular' => 'Bundle',
        'icon' => 'package',
        'meter' => 'bundles',
        'surface' => 'product',
        'description' => 'Quantity breaks, mix & match, fixed bundles, variant offers and gift bundles.',
        'empty' => 'Build your first bundle. Let shoppers buy more and save in one click.',
        'templates' => \App\Experiences\BundleSchema::templates(),
        'content' => [],
    ],

    // Progressive gifts: free shipping, gifts and order discounts in one progress bar (GiftSchema).
    'progressive-gifts' => [
        'label' => 'Progressive gifts',
        'discount' => true,
        'singular' => 'Progressive gifts',
        'icon' => 'gift',
        'meter' => 'free_gifts',
        'surface' => 'any',
        'description' => 'Rewards that unlock as the cart grows: free gifts, free shipping and discounts.',
        'empty' => 'Reward bigger carts with milestones shoppers can see.',
        'templates' => \App\Experiences\GiftSchema::templates(),
        'content' => [],
    ],

    'free-gifts' => [
        'retired' => true, // Replaced by Bundles / Progressive gifts; existing experiences keep working.
        'label' => 'Free Gifts',
        'discount' => true,
        'singular' => 'Free gift',
        'icon' => 'gift',
        'meter' => 'free_gifts',
        'surface' => 'cart',
        'description' => 'Spend thresholds that unlock a gift.',
        'empty' => 'Reward bigger carts. Set a threshold and a gift shoppers can see.',
        'templates' => [
            'minimal' => ['name' => 'Minimal', 'style' => 'minimal'],
            'progress-card' => ['name' => 'Progress Card', 'style' => 'card'],
            'reward-ladder' => ['name' => 'Reward Ladder', 'style' => 'ladder'],
            'premium-gift-card' => ['name' => 'Premium Gift Card', 'style' => 'premium'],
            'product-reveal' => ['name' => 'Product Reveal', 'style' => 'card'],
            'compact-cart-bar' => ['name' => 'Compact Cart Bar', 'style' => 'compact'],
        ],
        'content' => [
            'thresholds' => ['type' => 'list', 'label' => 'Thresholds', 'required' => true, 'max_items' => 4,
                'fields' => ['amount' => ['type' => 'money', 'label' => 'Spend', 'min' => 0], 'reward' => ['type' => 'text', 'label' => 'Reward', 'max' => 60]],
                'default' => [['amount' => 50, 'reward' => 'Free sample kit'], ['amount' => 100, 'reward' => 'Free full-size product']]],
            'gift_products' => ['type' => 'products', 'label' => 'Gift products', 'required' => true, 'max_items' => 4, 'help' => 'One gift unit is free for every threshold the cart reaches.'],
            'progress_message' => ['type' => 'text', 'label' => 'Progress message', 'default' => 'Spend {remaining} more to unlock {reward}', 'help' => 'Use {remaining} and {reward}.'],
            'unlocked_message' => ['type' => 'text', 'label' => 'Unlocked message', 'default' => 'You\'ve unlocked {reward}!'],
            'claim_mode' => ['type' => 'select', 'label' => 'When unlocked', 'default' => 'claim', 'options' => ['claim' => 'Shopper claims the gift', 'auto' => 'Add the gift automatically']],
            'checkout_label' => ['type' => 'text', 'label' => 'Discount name at checkout', 'default' => 'Free gift', 'max' => 60, 'help' => 'Shoppers see this next to the saving in cart and checkout.'],
        ],
    ],

    'shipping-bar' => [
        'retired' => true, // Replaced by Bundles / Progressive gifts; existing experiences keep working.
        'label' => 'Shipping Bar',
        'discount' => true,
        'singular' => 'Shipping bar',
        'icon' => 'truck',
        'meter' => 'shipping_bars',
        'surface' => 'any',
        'description' => 'Progress toward free shipping.',
        'empty' => 'Show shoppers how close they are to free shipping.',
        'templates' => [
            'minimal' => ['name' => 'Minimal', 'style' => 'minimal'],
            'progress' => ['name' => 'Progress', 'style' => 'banner'],
            'reward-ladder' => ['name' => 'Reward Ladder', 'style' => 'ladder'],
            'premium-card' => ['name' => 'Premium Card', 'style' => 'premium'],
        ],
        'content' => [
            'thresholds' => ['type' => 'list', 'label' => 'Thresholds', 'required' => true, 'max_items' => 3,
                'fields' => ['amount' => ['type' => 'money', 'label' => 'Cart value', 'min' => 0], 'reward' => ['type' => 'text', 'label' => 'Reward', 'max' => 60]],
                'default' => [['amount' => 60, 'reward' => 'free shipping']]],
            'progress_message' => ['type' => 'text', 'label' => 'Progress message', 'default' => 'You\'re {remaining} away from {reward}'],
            'unlocked_message' => ['type' => 'text', 'label' => 'Unlocked message', 'default' => 'You\'ve unlocked {reward}!'],
            'empty_message' => ['type' => 'text', 'label' => 'Empty cart message', 'default' => 'Free shipping on orders over {threshold}'],
            'free_shipping' => ['type' => 'toggle', 'label' => 'Give free shipping at the first threshold', 'default' => false, 'help' => 'Turn on if your shipping rates don\'t already include free shipping. OrderOrbit then applies it at checkout.'],
            'checkout_label' => ['type' => 'text', 'label' => 'Discount name at checkout', 'default' => 'Free shipping', 'max' => 60, 'help' => 'Shoppers see this next to the saving in cart and checkout.'],
        ],
    ],

    'quantity-breaks' => [
        'retired' => true, // Replaced by Bundles / Progressive gifts; existing experiences keep working.
        'label' => 'Quantity Breaks',
        'discount' => true,
        'singular' => 'Quantity break',
        'icon' => 'layers',
        'meter' => null,
        'surface' => 'product',
        'description' => 'Buy more, save more tiers.',
        'empty' => 'Sell more units with clear quantity discounts.',
        'templates' => [
            'tier-cards' => ['name' => 'Tier Cards', 'style' => 'card'],
            'radio-selector' => ['name' => 'Radio Selector', 'style' => 'minimal'],
            'horizontal-tiers' => ['name' => 'Horizontal Tiers', 'style' => 'row'],
            'premium-pricing-table' => ['name' => 'Premium Pricing Table', 'style' => 'table'],
            'compact-selector' => ['name' => 'Compact Selector', 'style' => 'compact'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Buy more, save more', 'max' => 80],
            'tiers' => ['type' => 'list', 'label' => 'Tiers', 'required' => true, 'max_items' => 6,
                'fields' => ['quantity' => ['type' => 'number', 'label' => 'Quantity', 'min' => 1, 'max' => 1000], 'discount' => ['type' => 'number', 'label' => '% off', 'min' => 0, 'max' => 100], 'badge' => ['type' => 'text', 'label' => 'Badge', 'max' => 24]],
                'default' => [['quantity' => 1, 'discount' => 0, 'badge' => ''], ['quantity' => 2, 'discount' => 10, 'badge' => ''], ['quantity' => 3, 'discount' => 20, 'badge' => 'Most popular']]],
            'products' => ['type' => 'products', 'label' => 'Products with quantity breaks', 'max_items' => 50, 'help' => 'Leave empty to use Targeting → Only these products. If both are empty, every product gets these tiers.'],
            'default_tier' => ['type' => 'number', 'label' => 'Selected tier', 'default' => 2, 'min' => 1, 'max' => 6, 'help' => 'Which tier is pre-selected (1 = first).'],
            'price_display' => ['type' => 'select', 'label' => 'Show price as', 'default' => 'per_unit', 'options' => ['per_unit' => 'Price per unit', 'total' => 'Total price']],
            'cta_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Add to cart', 'max' => 40],
            'checkout_label' => ['type' => 'text', 'label' => 'Discount name at checkout', 'default' => 'Volume discount', 'max' => 60, 'help' => 'Shoppers see this next to the saving in cart and checkout.'],
        ],
    ],

    'bogo' => [
        'retired' => true, // Replaced by Bundles / Progressive gifts; existing experiences keep working.
        'label' => 'BOGO',
        'discount' => true,
        'singular' => 'BOGO offer',
        'icon' => 'tag',
        'meter' => 'bundles',
        'surface' => 'product',
        'description' => 'Buy X, get Y free or discounted.',
        'empty' => 'Run a buy one, get one offer that applies at checkout.',
        'templates' => [
            'bogo-card' => ['name' => 'BOGO Card', 'style' => 'card'],
            'bogo-banner' => ['name' => 'Offer Banner', 'style' => 'banner'],
            'bogo-premium' => ['name' => 'Premium Offer', 'style' => 'premium'],
            'buy-x-get-y' => ['name' => 'Buy X Get Y', 'style' => 'row', 'content' => ['headline' => 'Buy 2, get 1 free', 'buy_quantity' => 2]],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Buy 1, get 1 free', 'max' => 80],
            'subheadline' => ['type' => 'text', 'label' => 'Subheadline', 'default' => 'Add two to your cart. The cheaper one is on us.', 'max' => 160],
            'buy_products' => ['type' => 'products', 'label' => 'Customer buys', 'required' => true, 'max_items' => 20],
            'buy_quantity' => ['type' => 'number', 'label' => 'Buy quantity', 'default' => 1, 'min' => 1, 'max' => 20],
            'get_products' => ['type' => 'products', 'label' => 'Customer gets', 'max_items' => 20, 'help' => 'Leave empty for the same products.'],
            'get_quantity' => ['type' => 'number', 'label' => 'Get quantity', 'default' => 1, 'min' => 1, 'max' => 20],
            'get_discount' => ['type' => 'number', 'label' => 'Discount on the items they get (%)', 'default' => 100, 'min' => 1, 'max' => 100, 'help' => '100 = free.'],
            'repeat' => ['type' => 'toggle', 'label' => 'Repeat for every set in the cart', 'default' => true],
            'cta_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Add offer to cart', 'max' => 40],
            'badge' => ['type' => 'text', 'label' => 'Badge', 'default' => 'BOGO', 'max' => 24],
            'checkout_label' => ['type' => 'text', 'label' => 'Discount name at checkout', 'default' => 'Buy one, get one', 'max' => 60, 'help' => 'Shoppers see this next to the saving in cart and checkout.'],
        ],
    ],

    'product-upsells' => [
        'retired' => true, // Replaced by Bundles / Progressive gifts; existing experiences keep working.
        'label' => 'Product Upsells',
        'discount' => true,
        'singular' => 'Product upsell',
        'icon' => 'sparkle',
        'meter' => null,
        'surface' => 'product',
        'description' => 'Recommend the next product on the product page.',
        'empty' => 'Recommend the next product at the right moment.',
        'templates' => [
            'product-card' => ['name' => 'Product Card', 'style' => 'card'],
            'side-by-side' => ['name' => 'Side-by-Side', 'style' => 'row'],
            'compact' => ['name' => 'Compact', 'style' => 'compact'],
            'premium-offer' => ['name' => 'Premium Offer', 'style' => 'premium'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Frequently added', 'max' => 80],
            'products' => ['type' => 'products', 'label' => 'Recommended products', 'required' => true, 'max_items' => 4],
            'offer_message' => ['type' => 'text', 'label' => 'Offer message', 'default' => 'Add it now and save 10%', 'max' => 80],
            'discount_percent' => ['type' => 'number', 'label' => 'Incentive (% off)', 'default' => 10, 'min' => 0, 'max' => 100, 'help' => 'Applies only to items added from this offer.'],
            'cta_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Add', 'max' => 24],
            'checkout_label' => ['type' => 'text', 'label' => 'Discount name at checkout', 'default' => 'Add-on offer', 'max' => 60, 'help' => 'Shoppers see this next to the saving in cart and checkout.'],
        ],
    ],

    'cart-upsells' => [
        'label' => 'Cart Upsells',
        'discount' => true,
        'singular' => 'Cart upsell',
        'icon' => 'bag',
        'meter' => null,
        'surface' => 'cart',
        'description' => 'Cart and cart-drawer recommendations.',
        'empty' => 'Suggest a last add-on in the cart.',
        'templates' => [
            'carousel' => ['name' => 'Carousel', 'style' => 'carousel'],
            'grid' => ['name' => 'Grid', 'style' => 'grid'],
            'horizontal' => ['name' => 'Horizontal', 'style' => 'row'],
            'minimal-card' => ['name' => 'Minimal Card', 'style' => 'minimal'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'You might also like', 'max' => 80],
            'products' => ['type' => 'products', 'label' => 'Recommended products', 'required' => true, 'max_items' => 8],
            'max_shown' => ['type' => 'number', 'label' => 'Products shown', 'default' => 3, 'min' => 1, 'max' => 8],
            'incentive' => ['type' => 'text', 'label' => 'Incentive text', 'default' => '', 'max' => 60],
            'discount_percent' => ['type' => 'number', 'label' => 'Incentive (% off)', 'default' => 0, 'min' => 0, 'max' => 100, 'help' => 'Applies only to items added from this offer.'],
            'cta_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Add', 'max' => 24],
            'checkout_label' => ['type' => 'text', 'label' => 'Discount name at checkout', 'default' => 'Cart offer', 'max' => 60, 'help' => 'Shoppers see this next to the saving in cart and checkout.'],
        ],
    ],

    'countdown' => [
        'label' => 'Countdown',
        'singular' => 'Countdown',
        'icon' => 'clock',
        'meter' => null,
        'surface' => 'any',
        'description' => 'Real deadlines for real campaigns, in eight layouts.',
        'empty' => 'Add a real deadline to a real campaign.',
        'templates' => [
            'minimal' => ['name' => 'Minimal', 'style' => 'minimal'],
            'banner' => ['name' => 'Banner', 'style' => 'banner', 'content' => ['cta_text' => 'Shop the sale']],
            'premium-card' => ['name' => 'Premium Card', 'style' => 'premium'],
            'offer-countdown' => ['name' => 'Offer Countdown', 'style' => 'card', 'content' => ['headline' => 'Extra 20% off ends soon', 'subheadline' => 'Use the code at checkout', 'code' => 'ORBIT20', 'cta_text' => 'Shop now']],
            'product-countdown' => ['name' => 'Product Countdown', 'style' => 'compact', 'content' => ['headline' => 'Offer ends in']],
            'flip-clock' => ['name' => 'Flip Clock', 'style' => 'flip', 'content' => ['headline' => 'Flash sale ends in']],
            'circles' => ['name' => 'Circles', 'style' => 'circles', 'content' => ['headline' => 'Hurry, the sale ends in']],
            'shipping-cutoff' => ['name' => 'Shipping Cutoff', 'style' => 'cutoff', 'content' => ['mode' => 'daily', 'headline' => 'Order within {time} to ship today', 'ended_message' => 'Order now to ship on the next business day']],
        ],
        'content' => [
            'mode' => ['type' => 'select', 'label' => 'Deadline', 'default' => 'date', 'options' => ['date' => 'A campaign end date', 'daily' => 'A daily cutoff time (e.g. same-day shipping)']],
            'ends_at' => ['type' => 'datetime', 'when' => ['mode' => 'date'], 'label' => 'Campaign ends', 'help' => 'A real deadline. Timers never reset per visitor.'],
            'daily_time' => ['type' => 'text', 'when' => ['mode' => 'daily'], 'label' => 'Daily cutoff (24h, store time)', 'default' => '14:00', 'max' => 5, 'help' => 'For example 14:00. After the cutoff it counts to the next day\'s cutoff.'],
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Sale ends in', 'max' => 80, 'help' => 'Shipping cutoff: use {time} for the time left.'],
            'subheadline' => ['type' => 'text', 'label' => 'Subheadline', 'default' => '', 'max' => 120],
            'code' => ['type' => 'text', 'label' => 'Discount code to show (optional)', 'default' => '', 'max' => 40, 'help' => 'Shoppers can copy it with one tap. Create the code in Shopify Discounts.'],
            'cta_text' => ['type' => 'text', 'label' => 'Button text (optional)', 'default' => '', 'max' => 30],
            'cta_url' => ['type' => 'text', 'label' => 'Button link', 'default' => '/collections/all', 'max' => 300],
            'labels' => ['type' => 'select', 'label' => 'Unit labels', 'default' => 'full', 'options' => ['full' => 'Days · Hours · Min · Sec', 'short' => 'd · h · m · s', 'none' => 'No labels']],
            'show_days' => ['type' => 'toggle', 'when' => ['mode' => 'date'], 'label' => 'Show days', 'default' => true, 'help' => 'Off: long timers show total hours.'],
            'urgency_hours' => ['type' => 'number', 'label' => 'Urgent colour in the last … hours', 'default' => 0, 'min' => 0, 'max' => 72, 'help' => '0 turns it off.'],
            'ended' => ['type' => 'select', 'label' => 'When it ends', 'default' => 'hide', 'options' => ['hide' => 'Hide the timer', 'message' => 'Show a message']],
            'ended_message' => ['type' => 'text', 'when' => ['ended' => 'message'], 'label' => 'Ended message', 'default' => 'This offer has ended', 'max' => 80],
        ],
    ],

    'sticky-atc' => [
        'label' => 'Sticky ATC',
        'singular' => 'Sticky add to cart',
        'icon' => 'cursor',
        'meter' => null,
        'surface' => 'product',
        'description' => 'Keep the buy button in reach, in three layouts.',
        'empty' => 'Keep the buy button in reach on long product pages.',
        'templates' => [
            'simple-sticky-bar' => ['name' => 'Simple sticky bar', 'style' => 'bar'],
            'floating-pill' => ['name' => 'Floating pill', 'style' => 'pill'],
            'full-width-bar' => ['name' => 'Full-width bar', 'style' => 'full', 'content' => ['show_compare' => true]],
        ],
        'content' => [
            'button_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Add to cart', 'max' => 30],
            'on_click' => ['type' => 'select', 'label' => 'When tapped', 'default' => 'scroll', 'options' => ['scroll' => 'Scroll to the product form', 'add' => 'Add to cart with your theme\'s button'], 'help' => 'Scrolling lets shoppers pick variants and options first.'],
            'show_image' => ['type' => 'toggle', 'label' => 'Show product image', 'default' => true],
            'show_title' => ['type' => 'toggle', 'label' => 'Show product title', 'default' => true],
            'show_price' => ['type' => 'toggle', 'label' => 'Show price', 'default' => true],
            'show_compare' => ['type' => 'toggle', 'label' => 'Show compare-at price', 'default' => false],
            'devices' => ['type' => 'select', 'label' => 'Show on', 'default' => 'all', 'options' => ['all' => 'Mobile and desktop', 'mobile' => 'Mobile only', 'desktop' => 'Desktop only']],
            'position' => ['type' => 'select', 'label' => 'Position', 'default' => 'bottom', 'options' => ['bottom' => 'Bottom of screen', 'top' => 'Top of screen']],
            'offset' => ['type' => 'number', 'label' => 'Distance from the edge (px)', 'default' => 12, 'min' => 0, 'max' => 120, 'help' => 'Raise it if a chat button overlaps.'],
        ],
    ],

    // Shown on every page by the app embed: no theme block to place. Purchases are real orders
    // recorded by the OrderOrbit pixel (SalesPopController feed); nothing is invented.
    'sales-pop' => [
        'label' => 'Sales pop',
        'singular' => 'Sales pop',
        'icon' => 'users',
        'meter' => null,
        'surface' => 'global',
        'description' => 'Recent-purchase notifications on every page, from your real orders.',
        'empty' => 'Show shoppers what others just bought.',
        'templates' => [
            'classic-card' => ['name' => 'Classic card', 'style' => 'card'],
            'rounded-pill' => ['name' => 'Rounded pill', 'style' => 'pill'],
            'dark-toast' => ['name' => 'Dark toast', 'style' => 'premium'],
            'minimal-text' => ['name' => 'Minimal text', 'style' => 'minimal', 'content' => ['show_image' => false]],
            'slim-bar' => ['name' => 'Slim bar', 'style' => 'banner', 'content' => ['position_desktop' => 'bottom-left', 'position_mobile' => 'top']],
        ],
        'content' => [
            'buyer_label' => ['type' => 'text', 'label' => 'Who bought it', 'default' => 'Someone', 'max' => 40, 'help' => 'Shown as {buyer}. Shopper names are never shown, so use a word like "Someone" or "A customer".'],
            'headline' => ['type' => 'text', 'label' => 'First line', 'default' => '{buyer} in {country}', 'max' => 80, 'help' => 'Use {buyer} and {country}. Without a country it shows just {buyer}.'],
            'action_text' => ['type' => 'text', 'label' => 'Before the product name', 'default' => 'purchased', 'max' => 40],
            'show_image' => ['type' => 'toggle', 'label' => 'Show product image', 'default' => true],
            'show_time' => ['type' => 'toggle', 'label' => 'Show when it was bought', 'default' => true, 'help' => 'The real time of the order, e.g. "12 minutes ago".'],
            'verified_text' => ['type' => 'text', 'label' => 'Verified label', 'default' => 'Verified purchase', 'max' => 40, 'help' => 'Leave empty to hide.'],
            'link_to_product' => ['type' => 'toggle', 'label' => 'Open the product when tapped', 'default' => true],
            'max_age_days' => ['type' => 'number', 'label' => 'Only purchases from the last … days', 'default' => 7, 'min' => 1, 'max' => 30],
            'match_product' => ['type' => 'toggle', 'label' => 'On product pages, show that product\'s purchases first', 'default' => false],
            'position_desktop' => ['type' => 'select', 'label' => 'Position on desktop', 'default' => 'bottom-left', 'options' => ['bottom-left' => 'Bottom left', 'bottom-right' => 'Bottom right', 'top-left' => 'Top left', 'top-right' => 'Top right', 'hide' => 'Don\'t show on desktop']],
            'position_mobile' => ['type' => 'select', 'label' => 'Position on mobile', 'default' => 'bottom', 'options' => ['bottom' => 'Bottom', 'top' => 'Top', 'bottom-left' => 'Bottom left (compact)', 'bottom-right' => 'Bottom right (compact)', 'hide' => 'Don\'t show on mobile']],
            'offset' => ['type' => 'number', 'label' => 'Distance from the edge (px)', 'default' => 16, 'min' => 0, 'max' => 160, 'help' => 'Raise it if a chat button or sticky bar overlaps.'],
            'first_delay' => ['type' => 'number', 'label' => 'First pop after (seconds)', 'default' => 5, 'min' => 0, 'max' => 120],
            'display_time' => ['type' => 'number', 'label' => 'Close automatically after (seconds)', 'default' => 6, 'min' => 2, 'max' => 60],
            'interval' => ['type' => 'number', 'label' => 'Gap between pops (seconds)', 'default' => 12, 'min' => 3, 'max' => 600],
            'random_gap' => ['type' => 'toggle', 'label' => 'Vary the gap randomly', 'default' => true, 'help' => 'Each gap is between half and one and a half times the setting, so it feels natural.'],
            'order' => ['type' => 'select', 'label' => 'Order', 'default' => 'random', 'options' => ['random' => 'Random', 'latest' => 'Newest first']],
            'max_per_page' => ['type' => 'number', 'label' => 'Most pops per page view', 'default' => 5, 'min' => 1, 'max' => 50],
            'loop' => ['type' => 'toggle', 'label' => 'Start again after the last purchase', 'default' => false],
            'pause_on_hover' => ['type' => 'toggle', 'label' => 'Pause while the shopper hovers', 'default' => true],
            'close_button' => ['type' => 'toggle', 'label' => 'Show a close button', 'default' => true, 'help' => 'Closing stops pops for the rest of the visit.'],
        ],
    ],

    'trust' => [
        'label' => 'Trust & Social Proof',
        'singular' => 'Trust block',
        'icon' => 'shield',
        'meter' => null,
        'surface' => 'any',
        'description' => 'Reviews, trust rows and guarantees.',
        'empty' => 'Show shoppers why they can trust you.',
        'templates' => [
            'review-card' => ['name' => 'Review Card', 'style' => 'card'],
            'review-slider' => ['name' => 'Review Slider', 'style' => 'slider'],
            'rating-strip' => ['name' => 'Rating Strip', 'style' => 'compact'],
            'customer-quote' => ['name' => 'Customer Quote', 'style' => 'premium'],
            'avatar-testimonials' => ['name' => 'Avatar Testimonials', 'style' => 'grid'],
            'trust-row' => ['name' => 'Trust Row', 'style' => 'row'],
            'guarantee-card' => ['name' => 'Guarantee Card', 'style' => 'banner'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Loved by our customers', 'max' => 80],
            'rating' => ['type' => 'number', 'label' => 'Average rating', 'default' => 4.9, 'min' => 0, 'max' => 5, 'step' => 0.1],
            'review_count' => ['type' => 'number', 'label' => 'Review count', 'default' => 1200, 'min' => 0, 'max' => 10000000],
            'reviews' => ['type' => 'list', 'label' => 'Reviews', 'max_items' => 8,
                'fields' => ['author' => ['type' => 'text', 'label' => 'Name', 'max' => 40], 'rating' => ['type' => 'number', 'label' => 'Stars', 'min' => 1, 'max' => 5], 'quote' => ['type' => 'text', 'label' => 'Quote', 'max' => 240]],
                'default' => [['author' => 'Sara K.', 'rating' => 5, 'quote' => 'My skin has never looked brighter.'], ['author' => 'Dev P.', 'rating' => 5, 'quote' => 'Fast shipping and a great guarantee.']]],
            'badges' => ['type' => 'list', 'label' => 'Trust badges', 'max_items' => 4,
                'fields' => ['icon' => ['type' => 'select', 'label' => 'Icon', 'options' => ['shipping' => 'Shipping', 'returns' => 'Returns', 'secure' => 'Secure', 'guarantee' => 'Guarantee', 'support' => 'Support']], 'label' => ['type' => 'text', 'label' => 'Text', 'max' => 40]],
                'default' => [['icon' => 'shipping', 'label' => 'Free shipping over $60'], ['icon' => 'returns', 'label' => '30-day returns'], ['icon' => 'secure', 'label' => 'Secure checkout']]],
            'guarantee' => ['type' => 'textarea', 'label' => 'Guarantee text', 'default' => '', 'max' => 400],
        ],
    ],

];
