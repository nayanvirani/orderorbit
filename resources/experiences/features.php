<?php

/*
|--------------------------------------------------------------------------
| App features (navigation)
|--------------------------------------------------------------------------
|
| What merchants see in the app menu and on the dashboard. Each feature
| groups one or more experience types (resources/experiences/types.php).
| "steps" explain how the feature works on the feature page.
|
*/

return [

    'bundles' => [
        'label' => 'Bundles',
        'types' => ['bundles'],
        'tagline' => 'Sell more per order with <em>bundles.</em>',
        'lead' => 'Mix & match, fixed bundles, frequently bought together and gift boxes. Shoppers add the whole bundle in one click and the saving applies automatically at checkout.',
        'steps' => [
            'Pick the products, bundle type and saving.',
            'Add the OrderOrbit block to your product page in the Theme Editor.',
            'Shoppers add the bundle in one click; the discount applies in cart and checkout.',
        ],
    ],

    'quantity-breaks' => [
        'label' => 'Volume discounts',
        'types' => ['quantity-breaks'],
        'tagline' => 'Buy more, <em>save more.</em>',
        'lead' => 'Quantity break tiers on the product page. Shoppers pick a tier, add that many to the cart, and the tier discount applies at checkout.',
        'steps' => [
            'Set your tiers: quantity and % off, with an optional badge.',
            'Choose which products get them, or apply them store-wide.',
            'Place the block under your product form. Discounts apply automatically.',
        ],
    ],

    'bogo' => [
        'label' => 'BOGO',
        'types' => ['bogo'],
        'tagline' => 'Buy one, <em>get one.</em>',
        'lead' => 'Buy X, get Y free or at a discount: same product or a different one. The offer adds both to the cart and the free items are taken off at checkout.',
        'steps' => [
            'Choose what shoppers buy and what they get.',
            'Set the quantities and the discount on the items they get.',
            'Place the block on the product page. Checkout applies the offer.',
        ],
    ],

    'free-gifts' => [
        'label' => 'Free gifts',
        'types' => ['free-gifts'],
        'tagline' => 'Reward bigger carts with a <em>free gift.</em>',
        'lead' => 'Spend thresholds that unlock a gift. Shoppers claim it (or it\'s added automatically) and it\'s free at checkout for as long as the cart qualifies.',
        'steps' => [
            'Set spend thresholds and choose the gift products.',
            'Decide whether shoppers claim the gift or it\'s added for them.',
            'Place the block in the cart. Gifts are free at checkout; extras are removed if the cart drops below the threshold.',
        ],
    ],

    'upsells' => [
        'label' => 'Upsells',
        'types' => ['product-upsells', 'cart-upsells'],
        'tagline' => 'The next product, <em>at the right moment.</em>',
        'lead' => 'Product-page and cart recommendations with one-click add. An optional incentive applies only to items added from the offer.',
        'steps' => [
            'Choose the products to recommend and an optional % off.',
            'Place the block on product pages or in the cart.',
            'Shoppers add with one click; the incentive applies at checkout.',
        ],
    ],

    'shipping-bar' => [
        'label' => 'Shipping bar',
        'types' => ['shipping-bar'],
        'tagline' => 'Show the way to <em>free shipping.</em>',
        'lead' => 'A live progress bar toward free shipping or other rewards. Optionally, OrderOrbit applies free shipping at checkout once the cart reaches the first threshold.',
        'steps' => [
            'Set the thresholds and messages.',
            'Turn on "Give free shipping" if your rates don\'t already include it.',
            'Place the bar on any page. It updates as the cart changes.',
        ],
    ],

    'countdown' => [
        'label' => 'Countdown timer',
        'types' => ['countdown'],
        'tagline' => 'Real deadlines for <em>real campaigns.</em>',
        'lead' => 'Countdown timers tied to a real end date. Timers never reset per visitor and hide or show a message when the campaign ends.',
        'steps' => [
            'Set the campaign end date and message.',
            'Place the timer on any page.',
            'It hides or changes its message automatically when time is up.',
        ],
    ],

    'sticky-atc' => [
        'label' => 'Sticky add to cart',
        'types' => ['sticky-atc'],
        'tagline' => 'Keep the buy button <em>in reach.</em>',
        'lead' => 'A slim add-to-cart bar that appears once your theme\'s buy button scrolls out of view, using your theme\'s own cart behaviour.',
        'steps' => [
            'Choose what the bar shows: image, price, button text.',
            'Place the block on your product template.',
            'The bar slides in when shoppers scroll past the buy button.',
        ],
    ],

    'trust' => [
        'label' => 'Trust badges',
        'types' => ['trust'],
        'tagline' => 'Show shoppers why they can <em>trust you.</em>',
        'lead' => 'Reviews, ratings, trust rows and guarantees that match your brand.',
        'steps' => [
            'Add your rating, reviews, badges or guarantee.',
            'Pick a template: review cards, slider, rating strip or trust row.',
            'Place it wherever shoppers hesitate: product page, cart or home.',
        ],
    ],

];
