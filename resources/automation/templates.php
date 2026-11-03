<?php

/*
| Automation templates (scope section 19). Each is a ready workflow definition: a trigger, its
| settings and steps, plus a category for the template gallery. Only follow-ups that Shopify
| (Flow, Messaging) and review apps don't already offer are kept: no review requests, welcomes,
| win-backs or cross-sells. Steps: action ({action, params}), wait ({amount, unit}) and condition
| ({match: all|any, rules: [{field, op, value}], then: [...], else: [...]}).
*/

$email = fn (string $subject, string $body) => ['type' => 'action', 'action' => 'send_email', 'params' => ['subject' => $subject, 'body' => $body]];
$wait = fn (int $amount, string $unit = 'days') => ['type' => 'wait', 'amount' => $amount, 'unit' => $unit];
$if = fn (array $rules, array $then, array $else = [], string $match = 'all') => ['type' => 'condition', 'match' => $match, 'rules' => $rules, 'then' => $then, 'else' => $else];
$rule = fn (string $field, string $op, $value) => ['field' => $field, 'op' => $op, 'value' => $value];
$tag = fn (string $action, string $tags) => ['type' => 'action', 'action' => $action, 'params' => ['tags' => $tags]];

return [
    'vip-customer' => [
        'category' => 'vip',
        'name' => 'VIP customer',
        'description' => 'Lifetime spend over a threshold → VIP tag, a unique thank-you code by email and a note to your team.',
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
        'category' => 'retention',
        'name' => 'Reorder reminder',
        'description' => 'Chosen products → wait → a reminder to reorder, skipped if they already have.',
        'trigger' => 'product_purchased',
        'trigger_config' => ['products' => []],
        'steps' => [
            $wait(30),
            $if([$rule('ordered_again', 'is', 'no')], [
                $email('Time to restock?', "Hi,\n\nIt's been about a month since you ordered {{product_titles}}. Running low? You can reorder in a couple of clicks.\n\n{{shop_name}}"),
            ]),
        ],
    ],
    'product-education' => [
        'category' => 'care',
        'name' => 'Product education',
        'description' => 'Purchase → delay → how-to and care guide.',
        'trigger' => 'order_fulfilled',
        'steps' => [
            $wait(3),
            $email('How to get the best from {{product_titles}}', "Hi,\n\nHere's our quick guide to using and caring for {{product_titles}}.\n\n{{shop_name}}"),
        ],
    ],
    'refund-follow-up' => [
        'category' => 'care',
        'name' => 'Refund follow-up',
        'description' => 'Refund → check in and create a support task.',
        'trigger' => 'refund_created',
        'steps' => [
            ['type' => 'action', 'action' => 'create_task', 'params' => ['title' => 'Follow up on refund for {{order_name}}', 'body' => 'Check what went wrong and whether we can help.', 'due_days' => 2]],
            $email('About your refund', "Hi,\n\nWe've processed a refund for {{order_name}}. We're sorry it didn't work out; if you have a moment, reply and tell us what we could do better.\n\n{{shop_name}}"),
        ],
    ],
    'cancellation-follow-up' => [
        'category' => 'care',
        'name' => 'Cancellation follow-up',
        'description' => 'Cancellation → ask for feedback and tag the order.',
        'trigger' => 'order_cancelled',
        'steps' => [
            $tag('add_order_tag', 'cancelled-follow-up'),
            $email('Sorry to see your order go', "Hi,\n\nYour order {{order_name}} was cancelled. If something got in the way, we'd love to know so we can fix it.\n\n{{shop_name}}"),
        ],
    ],
    'second-order-thanks' => [
        'category' => 'vip',
        'name' => 'Second-order thank-you',
        'description' => 'A customer\'s second order → tag them and email a unique code for the next one.',
        'trigger' => 'order_paid',
        'steps' => [
            $if([$rule('previous_orders', 'eq', 1)], [
                $tag('add_customer_tag', 'repeat-customer'),
                ['type' => 'action', 'action' => 'create_discount', 'params' => ['kind' => 'percent', 'value' => 10, 'expires_days' => 45, 'prefix' => 'AGAIN']],
                $email('Thanks for coming back', "Hi,\n\nThank you for ordering from us again. Here's 10% off your next order, just for you: {{discount_code}}\n\n{{shop_name}}"),
            ]),
        ],
    ],
    'survey-to-crm' => [
        'category' => 'growvia',
        'name' => 'Survey answer to your CRM',
        'description' => 'A post-purchase survey answer from Growvia → sent to your own tools and your team.',
        'trigger' => 'custom_event',
        'trigger_config' => ['event' => 'survey_answered'],
        'steps' => [
            ['type' => 'action', 'action' => 'webhook', 'params' => ['url' => '']],
            ['type' => 'action', 'action' => 'notify', 'params' => ['title' => 'New survey answer', 'body' => 'A shopper answered: {{event_label}}']],
        ],
    ],
];
