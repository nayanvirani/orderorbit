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
| datetime, products, collections, list (rows of sub-fields), image (an
| uploaded or linked https image).
|
| Template "style" picks the renderer variant: minimal, card, banner,
| premium, compact, ladder, grid, carousel, slider, table, row.
|
| Templates may preset content ("content" => [...]) and design ("design" => [...])
| on top of the type defaults.
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
        'meter' => 'cart_upsells',
        'surface' => 'cart',
        'description' => 'Cart recommendations with one-click add.',
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
        'description' => 'Countdowns to a date, hour or minute timers that can start again, or a daily cutoff, in eight layouts.',
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
            'mode' => ['type' => 'select', 'label' => 'Timer type', 'default' => 'date', 'options' => ['date' => 'Count down to a date', 'hours' => 'Hours, from when the shopper arrives', 'minutes' => 'Minutes, from when the shopper arrives', 'daily' => 'A daily cutoff time (e.g. same-day shipping)']],
            'ends_at' => ['type' => 'datetime', 'when' => ['mode' => 'date'], 'label' => 'Campaign ends', 'help' => 'The same deadline for every shopper.'],
            'hours' => ['type' => 'number', 'when' => ['mode' => 'hours'], 'label' => 'Timer length (hours)', 'default' => 2, 'min' => 1, 'max' => 72],
            'minutes' => ['type' => 'number', 'when' => ['mode' => 'minutes'], 'label' => 'Timer length (minutes)', 'default' => 10, 'min' => 1, 'max' => 240],
            'repeat' => ['type' => 'select', 'when' => ['mode' => ['hours', 'minutes']], 'label' => 'When it reaches zero', 'default' => 'restart', 'options' => ['restart' => 'Start again (e.g. every 10 minutes)', 'end' => 'Stop'], 'help' => 'The timer starts when the shopper first sees it and keeps counting across pages and visits.'],
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

    // Product-page block for the products the merchant picks. It can relabel the theme's add-to-cart
    // and adds a "Pre-order" line property so the order shows it; the product itself must allow
    // selling when out of stock in Shopify.
    'preorder' => [
        'label' => 'Pre-order',
        'singular' => 'Pre-order',
        'icon' => 'calendar',
        'meter' => 'preorders',
        'surface' => 'product',
        'description' => 'Take orders before stock arrives, with a ship date, progress bar and countdown.',
        'empty' => 'Take orders for products that aren\'t in stock yet.',
        'templates' => [
            'classic-card' => ['name' => 'Classic card', 'style' => 'card'],
            'minimal-line' => ['name' => 'Minimal line', 'style' => 'minimal', 'content' => ['progress' => 'none']],
            'timeline' => ['name' => 'Timeline steps', 'style' => 'timeline'],
            'countdown-tiles' => ['name' => 'Countdown tiles', 'style' => 'tiles', 'content' => ['breakdown' => 'months']],
            'goal-tracker' => ['name' => 'Goal tracker', 'style' => 'goal', 'content' => ['progress' => 'goal']],
            'premium-dark' => ['name' => 'Premium dark', 'style' => 'premium'],
            'banner' => ['name' => 'Banner', 'style' => 'banner'],
            'badge-pill' => ['name' => 'Badge pill', 'style' => 'pill', 'content' => ['progress' => 'none']],
        ],
        'content' => [
            'products' => ['type' => 'products', 'label' => 'Pre-order products', 'required' => true, 'max_items' => 50, 'help' => 'It only shows on these products. In Shopify, turn on "Continue selling when out of stock" for them.'],
            'show_when' => ['type' => 'select', 'label' => 'Show it', 'default' => 'always', 'options' => ['always' => 'Always on these products', 'sold_out' => 'Only when the selected variant is out of stock']],
            'badge_text' => ['type' => 'text', 'label' => 'Badge', 'default' => 'Pre-order', 'max' => 30],
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Available for pre-order', 'max' => 80],
            'message' => ['type' => 'text', 'label' => 'Message', 'default' => 'Ships by {date}. Order now to reserve yours.', 'max' => 160, 'help' => 'Use {date} for the ship date and {time} for the time left, e.g. "3 weeks 2 days".'],
            'ship_mode' => ['type' => 'select', 'label' => 'Ship date', 'default' => 'date', 'options' => ['date' => 'A fixed date', 'relative' => 'A number of days after the order']],
            'ship_date' => ['type' => 'datetime', 'when' => ['ship_mode' => 'date'], 'label' => 'Expected ship date'],
            'start_date' => ['type' => 'datetime', 'when' => ['ship_mode' => 'date'], 'label' => 'Pre-order opened', 'help' => 'Where the time progress bar starts. Leave empty to start 30 days before the ship date.'],
            'ship_days' => ['type' => 'number', 'when' => ['ship_mode' => 'relative'], 'label' => 'Ships after (days)', 'default' => 21, 'min' => 1, 'max' => 365],
            'breakdown' => ['type' => 'select', 'label' => 'Time left shown as', 'default' => 'auto', 'options' => ['auto' => 'Automatic', 'months' => 'Months, weeks and days', 'weeks' => 'Weeks and days', 'days' => 'Days']],
            'progress' => ['type' => 'select', 'label' => 'Progress bar', 'default' => 'time', 'options' => ['time' => 'Time until shipping', 'goal' => 'Units reserved toward a goal', 'none' => 'No progress bar']],
            'progress_label' => ['type' => 'text', 'when' => ['progress' => 'time'], 'label' => 'Time progress label', 'default' => 'Production progress', 'max' => 60],
            'goal_target' => ['type' => 'number', 'when' => ['progress' => 'goal'], 'label' => 'Goal (units)', 'default' => 100, 'min' => 1, 'max' => 1000000],
            'goal_current' => ['type' => 'number', 'when' => ['progress' => 'goal'], 'label' => 'Units reserved so far', 'default' => 0, 'min' => 0, 'max' => 1000000, 'help' => 'Use your real number and update it as orders come in.'],
            'goal_label' => ['type' => 'text', 'when' => ['progress' => 'goal'], 'label' => 'Goal label', 'default' => '{current} of {target} reserved', 'max' => 80],
            'change_button' => ['type' => 'toggle', 'label' => 'Change the add-to-cart button text', 'default' => true],
            'button_text' => ['type' => 'text', 'when' => ['change_button' => '1'], 'label' => 'Button text', 'default' => 'Pre-order now', 'max' => 30],
            'add_property' => ['type' => 'toggle', 'label' => 'Mark pre-order items in the cart and order', 'default' => true, 'help' => 'Adds a "Pre-order: Ships by …" line to the item, so you and the customer can see it.'],
            'property_name' => ['type' => 'text', 'when' => ['add_property' => '1'], 'label' => 'Line name', 'default' => 'Pre-order', 'max' => 30],
            'note' => ['type' => 'text', 'label' => 'Small print', 'default' => 'Payment is taken today. We\'ll email you when it ships.', 'max' => 200],
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

    /*
    | Checkout & post-purchase (Phase 6). Rendered by the orderorbit-checkout UI extension, not the
    | theme: "checkout" types inside Shopify checkout (Shopify Plus and development stores), and
    | "thank-you" types on the Thank You and Order Status pages (every plan). Checkout uses the
    | store's checkout branding, so these types have no design settings.
    */

    'checkout-reviews' => [
        'label' => 'Checkout reviews',
        'singular' => 'Checkout reviews block',
        'icon' => 'star',
        'meter' => null,
        'surface' => 'checkout',
        'description' => 'Customer reviews inside checkout, where shoppers decide.',
        'empty' => 'Reassure shoppers at the last step with real reviews.',
        'templates' => [
            'classic-review' => ['name' => 'Classic Review', 'style' => 'classic'],
            'modern-card' => ['name' => 'Modern Card', 'style' => 'card'],
            'minimal-slider' => ['name' => 'Minimal Slider', 'style' => 'slider'],
            'premium-testimonial' => ['name' => 'Premium Testimonial', 'style' => 'premium'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'What our customers say', 'max' => 80],
            'rating' => ['type' => 'number', 'label' => 'Average rating', 'default' => null, 'min' => 0, 'max' => 5, 'step' => 0.1, 'help' => 'Optional. Use your real rating from your reviews app.'],
            'review_count' => ['type' => 'number', 'label' => 'Number of reviews', 'default' => null, 'min' => 0, 'max' => 10000000],
            'reviews' => ['type' => 'list', 'label' => 'Reviews', 'required' => true, 'max_items' => 6, 'help' => 'Copy real reviews from your store.',
                'fields' => ['author' => ['type' => 'text', 'label' => 'Name', 'max' => 40], 'rating' => ['type' => 'number', 'label' => 'Stars', 'min' => 1, 'max' => 5], 'quote' => ['type' => 'text', 'label' => 'Quote', 'max' => 240]],
                'default' => []],
        ],
    ],

    'checkout-countdown' => [
        'label' => 'Checkout countdown',
        'singular' => 'Checkout countdown',
        'icon' => 'clock',
        'meter' => null,
        'surface' => 'checkout',
        'description' => 'A countdown inside checkout: to a date, or an hour or minute timer that can start again.',
        'empty' => 'Remind shoppers when a real offer ends.',
        'templates' => [
            'compact' => ['name' => 'Compact', 'style' => 'compact'],
            'banner' => ['name' => 'Banner', 'style' => 'banner'],
            'card' => ['name' => 'Card', 'style' => 'card'],
            'premium' => ['name' => 'Premium', 'style' => 'premium'],
        ],
        'content' => [
            'mode' => ['type' => 'select', 'label' => 'Timer type', 'default' => 'date', 'options' => ['date' => 'Count down to a date', 'hours' => 'Hours, from when the shopper reaches checkout', 'minutes' => 'Minutes, from when the shopper reaches checkout']],
            'ends_at' => ['type' => 'datetime', 'when' => ['mode' => 'date'], 'label' => 'Campaign ends', 'help' => 'The same deadline for every shopper.'],
            'hours' => ['type' => 'number', 'when' => ['mode' => 'hours'], 'label' => 'Timer length (hours)', 'default' => 2, 'min' => 1, 'max' => 72],
            'minutes' => ['type' => 'number', 'when' => ['mode' => 'minutes'], 'label' => 'Timer length (minutes)', 'default' => 10, 'min' => 1, 'max' => 240],
            'repeat' => ['type' => 'select', 'when' => ['mode' => ['hours', 'minutes']], 'label' => 'When it reaches zero', 'default' => 'restart', 'options' => ['restart' => 'Start again (e.g. every 10 minutes)', 'end' => 'Stop'], 'help' => 'The timer starts when the shopper first reaches checkout and keeps counting if they reload the page.'],
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Sale prices end in', 'max' => 80],
            'ended' => ['type' => 'select', 'label' => 'When it ends', 'default' => 'hide', 'options' => ['hide' => 'Hide the block', 'message' => 'Show a message'], 'help' => 'For a date, or a timer set to stop.'],
            'ended_message' => ['type' => 'text', 'when' => ['ended' => 'message'], 'label' => 'Ended message', 'default' => 'This offer has ended', 'max' => 80],
        ],
    ],

    'checkout-shipping' => [
        'label' => 'Checkout shipping progress',
        'singular' => 'Checkout shipping progress',
        'icon' => 'truck',
        'meter' => null,
        'surface' => 'checkout',
        'description' => 'Progress toward free shipping, from your Progressive gifts campaign.',
        'empty' => 'Show shoppers how close they are to free shipping, right in checkout.',
        'templates' => [
            'single-threshold' => ['name' => 'Single Threshold', 'style' => 'single', 'content' => ['milestones' => 'shipping']],
            'multi-threshold' => ['name' => 'Multi Threshold', 'style' => 'multi', 'content' => ['milestones' => 'all']],
            'reward-ladder' => ['name' => 'Reward Ladder', 'style' => 'ladder', 'content' => ['milestones' => 'all']],
        ],
        'content' => [
            'milestones' => ['type' => 'select', 'label' => 'Show', 'default' => 'shipping', 'options' => ['shipping' => 'Free shipping only', 'all' => 'Every reward in the campaign'], 'help' => 'Thresholds and rewards come from your live Progressive gifts campaign, so checkout and storefront always agree.'],
            'progress_message' => ['type' => 'text', 'label' => 'Progress message', 'default' => 'Add {remaining} more for {reward}', 'max' => 120, 'help' => 'Use {remaining} and {reward}.'],
            'unlocked_message' => ['type' => 'text', 'label' => 'Unlocked message', 'default' => 'You\'ve unlocked {reward}!', 'max' => 120],
        ],
    ],

    'checkout-gift' => [
        'label' => 'Checkout free gift',
        'singular' => 'Checkout free gift',
        'icon' => 'gift',
        'meter' => null,
        'surface' => 'checkout',
        'description' => 'Free-gift progress in checkout, with a one-tap claim, from your Progressive gifts campaign.',
        'empty' => 'Let shoppers claim their free gift without leaving checkout.',
        'templates' => [
            'progress' => ['name' => 'Progress', 'style' => 'progress'],
            'gift-unlocked' => ['name' => 'Gift Unlocked', 'style' => 'unlocked'],
            'reward-card' => ['name' => 'Reward Card', 'style' => 'card'],
        ],
        'content' => [
            'progress_message' => ['type' => 'text', 'label' => 'Progress message', 'default' => 'Add {remaining} more to get {reward}', 'max' => 120, 'help' => 'Gifts and thresholds come from your live Progressive gifts campaign. Use {remaining} and {reward}.'],
            'unlocked_message' => ['type' => 'text', 'label' => 'Unlocked message', 'default' => 'Your free gift is unlocked', 'max' => 120],
            'button_text' => ['type' => 'text', 'label' => 'Claim button', 'default' => 'Add free gift', 'max' => 30],
        ],
    ],

    'checkout-promo' => [
        'label' => 'Checkout promotion',
        'singular' => 'Checkout promotion',
        'icon' => 'sparkle',
        'meter' => null,
        'surface' => 'checkout',
        'description' => 'An announcement or offer inside checkout, with an optional one-tap code.',
        'empty' => 'Announce an offer at the moment of purchase.',
        'templates' => [
            'announcement' => ['name' => 'Announcement', 'style' => 'announcement'],
            'promotional-card' => ['name' => 'Promotional Card', 'style' => 'card'],
            'offer-banner' => ['name' => 'Offer Banner', 'style' => 'banner'],
            'premium' => ['name' => 'Premium', 'style' => 'premium'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'required' => true, 'default' => '', 'max' => 80],
            'message' => ['type' => 'text', 'label' => 'Message', 'default' => '', 'max' => 200],
            'code' => ['type' => 'text', 'label' => 'Discount code (optional)', 'default' => '', 'max' => 40, 'help' => 'Shoppers apply it with one tap. Create the code in Shopify Discounts first.'],
            'apply_text' => ['type' => 'text', 'label' => 'Apply button', 'default' => 'Apply code', 'max' => 30],
        ],
    ],

    'checkout-trust' => [
        'label' => 'Checkout trust',
        'singular' => 'Checkout trust block',
        'icon' => 'shield',
        'meter' => null,
        'surface' => 'checkout',
        'description' => 'Shipping, returns and guarantees shown inside checkout.',
        'empty' => 'Answer last-minute doubts right next to the pay button.',
        'templates' => [
            'trust-row' => ['name' => 'Trust Row', 'style' => 'row'],
            'icon-grid' => ['name' => 'Icon Grid', 'style' => 'grid'],
            'trust-card' => ['name' => 'Trust Card', 'style' => 'card'],
            'guarantee-banner' => ['name' => 'Guarantee Banner', 'style' => 'banner'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => '', 'max' => 80],
            'badges' => ['type' => 'list', 'label' => 'Trust points', 'max_items' => 4,
                'fields' => ['icon' => ['type' => 'select', 'label' => 'Icon', 'options' => ['shipping' => 'Shipping', 'returns' => 'Returns', 'secure' => 'Secure', 'guarantee' => 'Guarantee', 'support' => 'Support']], 'label' => ['type' => 'text', 'label' => 'Text', 'max' => 40]],
                'default' => [['icon' => 'secure', 'label' => 'Secure checkout'], ['icon' => 'returns', 'label' => 'Easy returns'], ['icon' => 'support', 'label' => 'Friendly support']]],
            'guarantee' => ['type' => 'textarea', 'label' => 'Guarantee text', 'default' => '', 'max' => 300],
        ],
    ],

    'checkout-image' => [
        'label' => 'Checkout image',
        'singular' => 'Checkout image',
        'icon' => 'layout',
        'meter' => null,
        'surface' => 'checkout',
        'description' => 'A banner, badge or lifestyle image inside checkout, with size, shape and link settings.',
        'empty' => 'Show a seasonal banner, payment badges or a guarantee seal in checkout.',
        'templates' => [
            'full-width' => ['name' => 'Full Width', 'style' => 'plain'],
            'wide-banner' => ['name' => 'Wide Banner', 'style' => 'plain', 'content' => ['img_height' => 'ratio', 'img_ratio' => '21/9', 'img_radius' => 'large']],
            'image-card' => ['name' => 'Image Card', 'style' => 'card', 'content' => ['img_height' => 'ratio', 'img_ratio' => '16/9', 'caption' => 'Free returns within 30 days']],
            'centered-logo' => ['name' => 'Centered Logo', 'style' => 'plain', 'content' => ['img_width' => 'px', 'img_width_value' => 160, 'img_radius' => 'none']],
            'framed' => ['name' => 'Framed', 'style' => 'card', 'content' => ['img_radius' => 'large'], 'design' => ['ck_background' => 'subdued', 'ck_border' => 'large', 'ck_border_style' => 'dashed', 'ck_radius' => 'large-100', 'ck_padding' => 'large']],
        ],
        'content' => [
            'image' => ['type' => 'image', 'label' => 'Image', 'required' => true, 'help' => 'Upload a JPG, PNG, GIF or WebP up to 5 MB (saved to your Shopify Files), or paste an https link.'],
            'alt' => ['type' => 'text', 'label' => 'Alt text', 'default' => '', 'max' => 200, 'help' => 'Describes the image for shoppers using screen readers.'],
            'caption' => ['type' => 'text', 'label' => 'Caption (optional)', 'default' => '', 'max' => 160],
            'link_url' => ['type' => 'text', 'label' => 'Link (optional)', 'default' => '', 'max' => 300, 'help' => 'Where the image goes when clicked: an https link or a store path like /collections/all.'],
            'img_width' => ['type' => 'select', 'label' => 'Image width', 'default' => 'fill', 'options' => ['fill' => 'Full width', 'px' => 'Fixed width (px)', 'percent' => 'Percent of the block']],
            'img_width_value' => ['type' => 'number', 'label' => 'Width value', 'default' => 240, 'min' => 10, 'max' => 1200, 'when' => ['img_width' => ['px', 'percent']], 'help' => 'Pixels, or 10–100 for a percentage.'],
            'img_height' => ['type' => 'select', 'label' => 'Image height', 'default' => 'natural', 'options' => ['natural' => 'Natural (keep the image\'s shape)', 'ratio' => 'Aspect ratio', 'px' => 'Fixed height (px)']],
            'img_ratio' => ['type' => 'select', 'label' => 'Aspect ratio', 'default' => '16/9', 'when' => ['img_height' => 'ratio'], 'options' => ['1/1' => 'Square 1:1', '4/3' => 'Landscape 4:3', '3/2' => 'Landscape 3:2', '16/9' => 'Wide 16:9', '21/9' => 'Banner 21:9', '3/4' => 'Portrait 3:4', '2/3' => 'Portrait 2:3']],
            'img_height_value' => ['type' => 'number', 'label' => 'Height (px)', 'default' => 200, 'min' => 20, 'max' => 1200, 'when' => ['img_height' => 'px']],
            'img_fit' => ['type' => 'select', 'label' => 'Fit', 'default' => 'cover', 'when' => ['img_height' => ['ratio', 'px']], 'options' => ['cover' => 'Fill the space (crops the edges)', 'contain' => 'Show the whole image']],
            'img_radius' => ['type' => 'select', 'label' => 'Image corner radius', 'default' => 'base', 'options' => array_diff_key(\App\Experiences\Schema::CHECKOUT_RADIUS, ['auto' => 1])],
            'img_border' => ['type' => 'select', 'label' => 'Image border', 'default' => 'none', 'options' => ['none' => 'No border', 'base' => 'Thin', 'large' => 'Medium', 'large-100' => 'Thick']],
            'img_border_style' => ['type' => 'select', 'label' => 'Image border style', 'default' => 'solid', 'when' => ['img_border' => ['base', 'large', 'large-100']], 'options' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted']],
            'align' => ['type' => 'select', 'label' => 'Alignment', 'default' => 'center', 'options' => ['start' => 'Left', 'center' => 'Centre', 'end' => 'Right']],
        ],
    ],

    'ty-cross-sell' => [
        'label' => 'Thank You cross-sell',
        'singular' => 'Thank You cross-sell',
        'icon' => 'bag',
        'meter' => null,
        'surface' => 'thank-you',
        'description' => 'Recommend the next product after the order is placed.',
        'empty' => 'Turn the Thank You page into the start of the next order.',
        'templates' => [
            'product-cards' => ['name' => 'Product Cards', 'style' => 'cards'],
            'compact-list' => ['name' => 'Compact List', 'style' => 'list'],
            'featured-product' => ['name' => 'Featured Product', 'style' => 'featured'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'You might also like', 'max' => 80],
            'products' => ['type' => 'products', 'label' => 'Products to recommend', 'required' => true, 'max_items' => 4],
            'message' => ['type' => 'text', 'label' => 'Message (optional)', 'default' => '', 'max' => 160, 'help' => 'For example a code for the next order.'],
            'button_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'View product', 'max' => 30],
            'pages' => ['type' => 'select', 'label' => 'Show on', 'default' => 'both', 'options' => ['both' => 'Thank You and Order Status', 'thank_you' => 'Thank You only', 'order_status' => 'Order Status only']],
        ],
    ],

    'ty-reorder' => [
        'label' => 'Reorder',
        'singular' => 'Reorder block',
        'icon' => 'repeat',
        'meter' => null,
        'surface' => 'thank-you',
        'description' => 'One click to order the same items again.',
        'empty' => 'Make the next order of the same items one click away.',
        'templates' => [
            'reorder-button' => ['name' => 'Reorder Button', 'style' => 'button'],
            'reorder-card' => ['name' => 'Reorder Card', 'style' => 'card'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Running low soon?', 'max' => 80],
            'message' => ['type' => 'text', 'label' => 'Message', 'default' => 'Order the same items again in one click.', 'max' => 160],
            'button_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Order again', 'max' => 30],
            'pages' => ['type' => 'select', 'label' => 'Show on', 'default' => 'order_status', 'options' => ['both' => 'Thank You and Order Status', 'thank_you' => 'Thank You only', 'order_status' => 'Order Status only']],
        ],
    ],

    'ty-review' => [
        'label' => 'Review request',
        'singular' => 'Review request',
        'icon' => 'star',
        'meter' => null,
        'surface' => 'thank-you',
        'description' => 'Ask for a review of the products just bought.',
        'empty' => 'Ask happy customers for a review at the right moment.',
        'templates' => [
            'simple-request' => ['name' => 'Simple Request', 'style' => 'simple'],
            'product-list' => ['name' => 'Product List', 'style' => 'products'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'How did we do?', 'max' => 80],
            'message' => ['type' => 'text', 'label' => 'Message', 'default' => 'Your review helps other shoppers and helps us improve.', 'max' => 160],
            'button_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Write a review', 'max' => 30],
            'review_url' => ['type' => 'text', 'label' => 'Review page link (optional)', 'default' => '', 'max' => 300, 'help' => 'Your reviews app\'s page. Leave empty to link each product page.'],
            'pages' => ['type' => 'select', 'label' => 'Show on', 'default' => 'order_status', 'options' => ['both' => 'Thank You and Order Status', 'thank_you' => 'Thank You only', 'order_status' => 'Order Status only']],
        ],
    ],

    'ty-referral' => [
        'label' => 'Referral',
        'singular' => 'Referral block',
        'icon' => 'users',
        'meter' => null,
        'surface' => 'thank-you',
        'description' => 'A code to share with friends.',
        'empty' => 'Turn customers into referrers with a code to share.',
        'templates' => [
            'share-card' => ['name' => 'Share Card', 'style' => 'card'],
            'referral-banner' => ['name' => 'Referral Banner', 'style' => 'banner'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Share with a friend', 'max' => 80],
            'message' => ['type' => 'text', 'label' => 'Message', 'default' => 'Give your friends this code for their first order.', 'max' => 160],
            'code' => ['type' => 'text', 'label' => 'Code to share', 'required' => true, 'default' => '', 'max' => 40, 'help' => 'Create the code in Shopify Discounts first.'],
            'share_url' => ['type' => 'text', 'label' => 'Link to share (optional)', 'default' => '', 'max' => 300, 'help' => 'Leave empty to share your store\'s home page.'],
            'pages' => ['type' => 'select', 'label' => 'Show on', 'default' => 'both', 'options' => ['both' => 'Thank You and Order Status', 'thank_you' => 'Thank You only', 'order_status' => 'Order Status only']],
        ],
    ],

    'ty-survey' => [
        'label' => 'Post-purchase survey',
        'singular' => 'Survey',
        'icon' => 'list',
        'meter' => null,
        'surface' => 'thank-you',
        'description' => 'One question after checkout, with answers in Analytics.',
        'empty' => 'Learn how customers found you with one quick question.',
        'templates' => [
            'choice-list' => ['name' => 'Choice List', 'style' => 'choice'],
            'survey-card' => ['name' => 'Survey Card', 'style' => 'card'],
        ],
        'content' => [
            'question' => ['type' => 'text', 'label' => 'Question', 'default' => 'How did you hear about us?', 'max' => 120],
            'options' => ['type' => 'list', 'label' => 'Answers', 'required' => true, 'max_items' => 8,
                'fields' => ['label' => ['type' => 'text', 'label' => 'Answer', 'max' => 60]],
                'default' => [['label' => 'Instagram'], ['label' => 'Google'], ['label' => 'A friend'], ['label' => 'Other']]],
            'button_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Send', 'max' => 30],
            'thanks_message' => ['type' => 'text', 'label' => 'Thank-you message', 'default' => 'Thanks for telling us!', 'max' => 120],
            'pages' => ['type' => 'select', 'label' => 'Show on', 'default' => 'thank_you', 'options' => ['both' => 'Thank You and Order Status', 'thank_you' => 'Thank You only', 'order_status' => 'Order Status only']],
        ],
    ],

    'ty-discount' => [
        'label' => 'Next-purchase discount',
        'singular' => 'Next-purchase discount',
        'icon' => 'tag',
        'meter' => null,
        'surface' => 'thank-you',
        'description' => 'A code for the customer\'s next order.',
        'empty' => 'Give a reason to come back with a code for next time.',
        'templates' => [
            'code-card' => ['name' => 'Code Card', 'style' => 'card'],
            'code-banner' => ['name' => 'Code Banner', 'style' => 'banner'],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'A thank-you for next time', 'max' => 80],
            'message' => ['type' => 'text', 'label' => 'Message', 'default' => 'Use this code on your next order.', 'max' => 160],
            'code' => ['type' => 'text', 'label' => 'Discount code', 'required' => true, 'default' => '', 'max' => 40, 'help' => 'Create the code in Shopify Discounts first.'],
            'expiry_text' => ['type' => 'text', 'label' => 'Expiry note (optional)', 'default' => '', 'max' => 60, 'help' => 'For example "Valid for 30 days". Match the code\'s real end date.'],
            'pages' => ['type' => 'select', 'label' => 'Show on', 'default' => 'both', 'options' => ['both' => 'Thank You and Order Status', 'thank_you' => 'Thank You only', 'order_status' => 'Order Status only']],
        ],
    ],

    'ty-message' => [
        'label' => 'Message & links',
        'singular' => 'Message block',
        'icon' => 'message',
        'meter' => null,
        'surface' => 'thank-you',
        'description' => 'Loyalty news, product care tips or tracking and support links.',
        'empty' => 'Share what customers need to know after they buy.',
        'templates' => [
            'loyalty-message' => ['name' => 'Loyalty Message', 'style' => 'loyalty', 'content' => ['headline' => 'You earned points with this order', 'message' => 'See your rewards balance in your account.', 'button_text' => 'View rewards']],
            'product-education' => ['name' => 'Product Education', 'style' => 'education', 'content' => ['headline' => 'Get the most from your order', 'message' => 'Read our care and how-to guide.', 'button_text' => 'Read the guide']],
            'tracking-support' => ['name' => 'Tracking & Support', 'style' => 'support', 'content' => ['headline' => 'Questions about your order?', 'message' => 'We\'re here to help with delivery, returns and anything else.', 'button_text' => 'Contact support']],
        ],
        'content' => [
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => '', 'max' => 80],
            'message' => ['type' => 'textarea', 'label' => 'Message', 'default' => '', 'max' => 400],
            'button_text' => ['type' => 'text', 'label' => 'Button text (optional)', 'default' => '', 'max' => 30],
            'button_url' => ['type' => 'text', 'label' => 'Button link', 'default' => '', 'max' => 300],
            'pages' => ['type' => 'select', 'label' => 'Show on', 'default' => 'both', 'options' => ['both' => 'Thank You and Order Status', 'thank_you' => 'Thank You only', 'order_status' => 'Order Status only']],
        ],
    ],

    'ty-image' => [
        'label' => 'Thank You image',
        'singular' => 'Thank You image',
        'icon' => 'layout',
        'meter' => null,
        'surface' => 'thank-you',
        'description' => 'An image on the Thank You and Order Status pages, with size, shape and link settings.',
        'empty' => 'Thank customers with a brand image, or link a banner to your next collection.',
        'templates' => [
            'full-width' => ['name' => 'Full Width', 'style' => 'plain'],
            'wide-banner' => ['name' => 'Wide Banner', 'style' => 'plain', 'content' => ['img_height' => 'ratio', 'img_ratio' => '21/9', 'img_radius' => 'large']],
            'image-card' => ['name' => 'Image Card', 'style' => 'card', 'content' => ['img_height' => 'ratio', 'img_ratio' => '16/9', 'caption' => 'Free returns within 30 days']],
            'centered-logo' => ['name' => 'Centered Logo', 'style' => 'plain', 'content' => ['img_width' => 'px', 'img_width_value' => 160, 'img_radius' => 'none']],
            'framed' => ['name' => 'Framed', 'style' => 'card', 'content' => ['img_radius' => 'large'], 'design' => ['ck_background' => 'subdued', 'ck_border' => 'large', 'ck_border_style' => 'dashed', 'ck_radius' => 'large-100', 'ck_padding' => 'large']],
        ],
        'content' => [
            'image' => ['type' => 'image', 'label' => 'Image', 'required' => true, 'help' => 'Upload a JPG, PNG, GIF or WebP up to 5 MB (saved to your Shopify Files), or paste an https link.'],
            'alt' => ['type' => 'text', 'label' => 'Alt text', 'default' => '', 'max' => 200, 'help' => 'Describes the image for shoppers using screen readers.'],
            'caption' => ['type' => 'text', 'label' => 'Caption (optional)', 'default' => '', 'max' => 160],
            'link_url' => ['type' => 'text', 'label' => 'Link (optional)', 'default' => '', 'max' => 300, 'help' => 'Where the image goes when clicked: an https link or a store path like /collections/all.'],
            'img_width' => ['type' => 'select', 'label' => 'Image width', 'default' => 'fill', 'options' => ['fill' => 'Full width', 'px' => 'Fixed width (px)', 'percent' => 'Percent of the block']],
            'img_width_value' => ['type' => 'number', 'label' => 'Width value', 'default' => 240, 'min' => 10, 'max' => 1200, 'when' => ['img_width' => ['px', 'percent']], 'help' => 'Pixels, or 10–100 for a percentage.'],
            'img_height' => ['type' => 'select', 'label' => 'Image height', 'default' => 'natural', 'options' => ['natural' => 'Natural (keep the image\'s shape)', 'ratio' => 'Aspect ratio', 'px' => 'Fixed height (px)']],
            'img_ratio' => ['type' => 'select', 'label' => 'Aspect ratio', 'default' => '16/9', 'when' => ['img_height' => 'ratio'], 'options' => ['1/1' => 'Square 1:1', '4/3' => 'Landscape 4:3', '3/2' => 'Landscape 3:2', '16/9' => 'Wide 16:9', '21/9' => 'Banner 21:9', '3/4' => 'Portrait 3:4', '2/3' => 'Portrait 2:3']],
            'img_height_value' => ['type' => 'number', 'label' => 'Height (px)', 'default' => 200, 'min' => 20, 'max' => 1200, 'when' => ['img_height' => 'px']],
            'img_fit' => ['type' => 'select', 'label' => 'Fit', 'default' => 'cover', 'when' => ['img_height' => ['ratio', 'px']], 'options' => ['cover' => 'Fill the space (crops the edges)', 'contain' => 'Show the whole image']],
            'img_radius' => ['type' => 'select', 'label' => 'Image corner radius', 'default' => 'base', 'options' => array_diff_key(\App\Experiences\Schema::CHECKOUT_RADIUS, ['auto' => 1])],
            'img_border' => ['type' => 'select', 'label' => 'Image border', 'default' => 'none', 'options' => ['none' => 'No border', 'base' => 'Thin', 'large' => 'Medium', 'large-100' => 'Thick']],
            'img_border_style' => ['type' => 'select', 'label' => 'Image border style', 'default' => 'solid', 'when' => ['img_border' => ['base', 'large', 'large-100']], 'options' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted']],
            'align' => ['type' => 'select', 'label' => 'Alignment', 'default' => 'center', 'options' => ['start' => 'Left', 'center' => 'Centre', 'end' => 'Right']],
            'pages' => ['type' => 'select', 'label' => 'Show on', 'default' => 'both', 'options' => ['both' => 'Thank You and Order Status', 'thank_you' => 'Thank You only', 'order_status' => 'Order Status only']],
        ],
    ],

    // Post-purchase funnel: a one-click offer between payment and the Thank You page, drawn by the
    // orderorbit-post-purchase extension. The app picks the offer and signs the order change.
    'post-purchase' => [
        'label' => 'Post-purchase offer',
        'singular' => 'Post-purchase funnel',
        'icon' => 'bolt',
        'meter' => null,
        'surface' => 'post-purchase',
        'description' => 'A one-click upsell after payment, with an optional downsell if declined.',
        'empty' => 'Add one more product to the order in one click, with no need to pay again.',
        'templates' => [
            'classic-offer' => ['name' => 'Classic Offer', 'style' => 'classic'],
            'offer-with-downsell' => ['name' => 'Offer + Downsell', 'style' => 'classic', 'content' => ['downsell' => true]],
            'minimal-offer' => ['name' => 'Minimal Offer', 'style' => 'minimal'],
            'premium-offer' => ['name' => 'Premium Offer', 'style' => 'premium'],
        ],
        'content' => [
            'trigger' => ['type' => 'select', 'label' => 'Show it after', 'default' => 'any', 'options' => ['any' => 'Every order', 'products' => 'Orders with certain products', 'min_total' => 'Orders above an amount']],
            'trigger_products' => ['type' => 'products', 'when' => ['trigger' => 'products'], 'label' => 'Orders containing any of', 'max_items' => 20],
            'min_total' => ['type' => 'money', 'when' => ['trigger' => 'min_total'], 'label' => 'Order total at least', 'min' => 0],
            'offer_product' => ['type' => 'products', 'label' => 'Offer', 'required' => true, 'max_items' => 1, 'help' => 'Shoppers add it to the order they just paid for, in one click.'],
            'discount_percent' => ['type' => 'number', 'label' => 'Discount on the offer (%)', 'default' => 15, 'min' => 0, 'max' => 90],
            'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Add this to your order', 'max' => 80],
            'message' => ['type' => 'text', 'label' => 'Message', 'default' => 'One click, and it ships with your order. No need to enter your payment details again.', 'max' => 200],
            'accept_text' => ['type' => 'text', 'label' => 'Accept button', 'default' => 'Pay now', 'max' => 30],
            'decline_text' => ['type' => 'text', 'label' => 'Decline button', 'default' => 'Decline this offer', 'max' => 30],
            'downsell' => ['type' => 'toggle', 'label' => 'Offer something else if they decline', 'default' => false],
            'downsell_product' => ['type' => 'products', 'when' => ['downsell' => '1'], 'label' => 'Second offer', 'max_items' => 1],
            'downsell_discount' => ['type' => 'number', 'when' => ['downsell' => '1'], 'label' => 'Discount on the second offer (%)', 'default' => 20, 'min' => 0, 'max' => 90],
            'downsell_headline' => ['type' => 'text', 'when' => ['downsell' => '1'], 'label' => 'Second offer headline', 'default' => 'How about this instead?', 'max' => 80],
        ],
    ],

];
