<?php

/*
| Automation templates (scope section 19). Each is a ready workflow definition: a trigger, its
| settings and steps. Steps: action ({action, params}), wait ({amount, unit}) and condition
| ({match: all|any, rules: [{field, op, value}], then: [...], else: [...]}).
*/

$email = fn (string $subject, string $body) => ['type' => 'action', 'action' => 'send_email', 'params' => ['subject' => $subject, 'body' => $body]];
$wait = fn (int $amount, string $unit = 'days') => ['type' => 'wait', 'amount' => $amount, 'unit' => $unit];
$if = fn (array $rules, array $then, array $else = [], string $match = 'all') => ['type' => 'condition', 'match' => $match, 'rules' => $rules, 'then' => $then, 'else' => $else];
$rule = fn (string $field, string $op, $value) => ['field' => $field, 'op' => $op, 'value' => $value];
$tag = fn (string $action, string $tags) => ['type' => 'action', 'action' => $action, 'params' => ['tags' => $tags]];

return [
    'review-request' => [
        'name' => 'Review request',
        'description' => 'Delivered → wait 5 days → ask for a review.',
        'trigger' => 'order_delivered',
        'steps' => [
            $wait(5),
            $email('How are you finding your order?', "Hi,\n\nYour order {{order_name}} arrived a few days ago. We'd love to hear what you think: a quick review helps other shoppers and helps us improve.\n\nThank you,\n{{shop_name}}"),
        ],
    ],
    'delivery-follow-up' => [
        'name' => 'Delivery follow-up',
        'description' => 'Delivered → care tips and how to get help.',
        'trigger' => 'order_delivered',
        'steps' => [
            $wait(1),
            $email('Getting started with your order', "Hi,\n\nYour order {{order_name}} has arrived. Here are a few tips to get the most from {{product_titles}}. If anything isn't right, just reply to this email.\n\n{{shop_name}}"),
        ],
    ],
    'new-customer-welcome' => [
        'name' => 'New customer welcome',
        'description' => 'First order → welcome and tag the customer.',
        'trigger' => 'order_paid',
        'steps' => [
            $if([$rule('customer', 'is', 'new')], [
                $tag('add_customer_tag', 'new-customer'),
                $email('Welcome to {{shop_name}}', "Hi,\n\nThank you for your first order, {{order_name}}. We're glad you're here.\n\n{{shop_name}}"),
            ]),
        ],
    ],
    'vip-customer' => [
        'name' => 'VIP customer',
        'description' => 'Lifetime spend over a threshold → VIP tag and a thank-you code.',
        'trigger' => 'order_paid',
        'steps' => [
            $if([$rule('ltv', 'gte', 500), $rule('customer_tag', 'has_not', 'vip')], [
                $tag('add_customer_tag', 'vip'),
                ['type' => 'action', 'action' => 'create_discount', 'params' => ['kind' => 'percent', 'value' => 15, 'expires_days' => 60, 'prefix' => 'VIP']],
                $email('You\'re one of our best customers', "Hi,\n\nAs a thank-you for being one of our best customers, here's 15% off your next order: {{discount_code}}\n\n{{shop_name}}"),
                ['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'New VIP customer', 'body' => 'Order {{order_name}} took a customer past $500 lifetime spend.']],
            ]),
        ],
    ],
    'reorder-reminder' => [
        'name' => 'Reorder reminder',
        'description' => 'Purchase → product-specific delay → reminder to reorder.',
        'trigger' => 'product_purchased',
        'trigger_config' => ['products' => []],
        'steps' => [
            $wait(30),
            $if([$rule('ordered_again', 'is', 'no')], [
                $email('Time to restock?', "Hi,\n\nIt's been about a month since you ordered {{product_titles}}. Running low? You can reorder in a couple of clicks.\n\n{{shop_name}}"),
            ]),
        ],
    ],
    'win-back' => [
        'name' => 'Win-back',
        'description' => 'No order in 90 days → an offer to come back.',
        'trigger' => 'order_paid',
        'steps' => [
            $wait(90),
            $if([$rule('ordered_again', 'is', 'no')], [
                ['type' => 'action', 'action' => 'create_discount', 'params' => ['kind' => 'percent', 'value' => 20, 'expires_days' => 14, 'prefix' => 'COMEBACK']],
                $email('We miss you', "Hi,\n\nIt's been a while! Here's 20% off your next order, valid for 14 days: {{discount_code}}\n\n{{shop_name}}"),
            ]),
        ],
    ],
    'cross-sell' => [
        'name' => 'Cross-sell',
        'description' => 'Product purchased → recommend a related product.',
        'trigger' => 'product_purchased',
        'trigger_config' => ['products' => []],
        'steps' => [
            $wait(7),
            $email('Goes great with your {{product_titles}}', "Hi,\n\nCustomers who bought {{product_titles}} often add our matching products. Take a look.\n\n{{shop_name}}"),
        ],
    ],
    'product-education' => [
        'name' => 'Product education',
        'description' => 'Purchase → delay → how-to and care guide.',
        'trigger' => 'order_fulfilled',
        'steps' => [
            $wait(3),
            $email('How to get the best from {{product_titles}}', "Hi,\n\nHere's our quick guide to using and caring for {{product_titles}}.\n\n{{shop_name}}"),
        ],
    ],
    'refund-follow-up' => [
        'name' => 'Refund follow-up',
        'description' => 'Refund → check in and create a support task.',
        'trigger' => 'refund_created',
        'steps' => [
            ['type' => 'action', 'action' => 'create_task', 'params' => ['title' => 'Follow up on refund for {{order_name}}', 'body' => 'Check what went wrong and whether we can help.', 'due_days' => 2]],
            $email('About your refund', "Hi,\n\nWe've processed a refund for {{order_name}}. We're sorry it didn't work out; if you have a moment, reply and tell us what we could do better.\n\n{{shop_name}}"),
        ],
    ],
    'cancellation-follow-up' => [
        'name' => 'Cancellation follow-up',
        'description' => 'Cancellation → ask for feedback and tag the order.',
        'trigger' => 'order_cancelled',
        'steps' => [
            $tag('add_order_tag', 'cancelled-follow-up'),
            $email('Sorry to see your order go', "Hi,\n\nYour order {{order_name}} was cancelled. If something got in the way, we'd love to know so we can fix it.\n\n{{shop_name}}"),
        ],
    ],
];
