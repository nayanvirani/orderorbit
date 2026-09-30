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
        'icon' => 'package',
        'tone' => 'bundles',
        'label' => 'Bundles',
        'types' => ['bundles'],
        'module' => 'app.bundles.index',
        'tagline' => 'Sell more per order with <em>bundles.</em>',
        'lead' => 'Mix & match, fixed bundles, frequently bought together and gift boxes. Shoppers add the whole bundle in one click and the saving applies automatically at checkout.',
        'steps' => [
            'Pick the products, bundle type and saving.',
            'Add the OrderOrbit Space block to your product page in the Theme Editor.',
            'Shoppers add the bundle in one click; the discount applies in cart and checkout.',
        ],
    ],

    'progressive-gifts' => [
        'icon' => 'gift',
        'tone' => 'gifts',
        'label' => 'Progressive gifts',
        'types' => ['progressive-gifts'],
        'module' => 'app.gifts.index',
        'tagline' => 'Rewards that grow <em>with the cart.</em>',
        'lead' => 'Free gifts, free shipping and discounts that unlock by cart value or item count, in one progress bar.',
        'steps' => [
            'Set your milestones and their rewards.',
            'Pick a layout: bar, strip, steps, cards or radial.',
            'It shows under the add to cart button; rewards apply at checkout.',
        ],
    ],

    'cart-upsells' => [
        'icon' => 'cart',
        'tone' => 'upsells',
        'label' => 'Cart upsells',
        'types' => ['cart-upsells'],
        'tagline' => 'One last add-on <em>in the cart.</em>',
        'lead' => 'Cart recommendations with one-click add. An optional incentive applies only to items added from the offer.',
        'steps' => [
            'Choose the products to recommend and an optional % off.',
            'Place the block in your cart.',
            'Shoppers add with one click; the incentive applies at checkout.',
        ],
    ],

    'countdown' => [
        'icon' => 'clock',
        'tone' => 'countdown',
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
        'icon' => 'cursor',
        'tone' => 'sticky',
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
        'icon' => 'shield',
        'tone' => 'trust',
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
