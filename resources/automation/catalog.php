<?php

/*
|--------------------------------------------------------------------------
| Automation catalogue (Phase 7)
|--------------------------------------------------------------------------
|
| What the workflow builder offers and the engine understands. Field types:
| text, textarea, number, select, products, collections, workflow.
| Placeholders usable in text actions: {{order_name}}, {{order_total}},
| {{product_titles}}, {{shop_name}}, {{discount_code}}, {{event_name}}.
|
*/

return [

    'triggers' => [
        'order_created' => ['label' => 'Order created', 'group' => 'Orders', 'help' => 'When a new order is placed.'],
        'order_paid' => ['label' => 'Order paid', 'group' => 'Orders', 'help' => 'When an order is fully paid.'],
        'order_fulfilled' => ['label' => 'Order fulfilled', 'group' => 'Orders', 'help' => 'When every item in an order is fulfilled.'],
        'order_delivered' => ['label' => 'Order delivered', 'group' => 'Orders', 'help' => 'When the carrier marks a shipment delivered (needs tracking that reports delivery).'],
        'order_cancelled' => ['label' => 'Order cancelled', 'group' => 'Orders', 'help' => 'When an order is cancelled.'],
        'refund_created' => ['label' => 'Refund created', 'group' => 'Orders', 'help' => 'When an order is refunded, fully or partly.'],
        'product_purchased' => ['label' => 'Product purchased', 'group' => 'Orders', 'help' => 'When an order contains one of the chosen products.', 'config' => [
            'products' => ['type' => 'products', 'label' => 'Products', 'required' => true],
        ]],
        'customer_created' => ['label' => 'Customer created', 'group' => 'Customers', 'help' => 'When a new customer account is created.'],
        'customer_tag_added' => ['label' => 'Customer tag added', 'group' => 'Customers', 'help' => 'When a customer gets a tag.', 'config' => [
            'tag' => ['type' => 'text', 'label' => 'Tag', 'required' => true],
        ]],
        'custom_event' => ['label' => 'OrderOrbit event', 'group' => 'OrderOrbit', 'help' => 'When a shopper does something in an OrderOrbit Space offer.', 'config' => [
            'event' => ['type' => 'select', 'label' => 'Event', 'required' => true, 'options' => [
                'survey_answered' => 'Survey answered', 'reward_unlocked' => 'Reward unlocked', 'upsell_accepted' => 'Upsell accepted', 'added_to_cart' => 'Added to cart from an offer',
            ]],
        ]],
    ],

    'conditions' => [
        'order_total' => ['label' => 'Order total', 'type' => 'number', 'ops' => ['gte', 'lte', 'gt', 'lt', 'eq']],
        'quantity' => ['label' => 'Number of items', 'type' => 'number', 'ops' => ['gte', 'lte', 'gt', 'lt', 'eq']],
        'product' => ['label' => 'Product', 'type' => 'products', 'ops' => ['contains', 'not_contains']],
        'variant' => ['label' => 'Variant ID', 'type' => 'text', 'ops' => ['contains', 'not_contains'], 'help' => 'Variant IDs separated by commas.'],
        'sku' => ['label' => 'SKU', 'type' => 'text', 'ops' => ['contains', 'not_contains']],
        'collection' => ['label' => 'Collection', 'type' => 'collections', 'ops' => ['contains', 'not_contains']],
        'customer' => ['label' => 'Customer', 'type' => 'select', 'ops' => ['is'], 'options' => ['new' => 'First order', 'returning' => 'Returning customer']],
        'customer_tag' => ['label' => 'Customer tag', 'type' => 'text', 'ops' => ['has', 'has_not']],
        'country' => ['label' => 'Shipping country', 'type' => 'text', 'ops' => ['in', 'not_in'], 'help' => 'Two-letter codes separated by commas, e.g. US, CA.'],
        'province' => ['label' => 'Shipping state / province', 'type' => 'text', 'ops' => ['in', 'not_in'], 'help' => 'Codes separated by commas, e.g. CA, NY.'],
        'shipping_method' => ['label' => 'Shipping method', 'type' => 'text', 'ops' => ['contains', 'not_contains']],
        'payment_method' => ['label' => 'Payment method', 'type' => 'text', 'ops' => ['contains', 'not_contains']],
        'fulfillment_status' => ['label' => 'Fulfillment status', 'type' => 'select', 'ops' => ['is', 'is_not'], 'options' => ['unfulfilled' => 'Unfulfilled', 'partial' => 'Partly fulfilled', 'fulfilled' => 'Fulfilled']],
        'previous_orders' => ['label' => 'Previous orders', 'type' => 'number', 'ops' => ['gte', 'lte', 'eq']],
        'ltv' => ['label' => 'Customer lifetime spend', 'type' => 'number', 'ops' => ['gte', 'lte']],
        'days_since_order' => ['label' => 'Days since the order', 'type' => 'number', 'ops' => ['gte', 'lte']],
        'ordered_again' => ['label' => 'Ordered again since the trigger', 'type' => 'select', 'ops' => ['is'], 'options' => ['yes' => 'Yes', 'no' => 'No'], 'help' => 'Checks the customer\'s latest order at this step. Useful for win-backs.'],
        'event_property' => ['label' => 'Event answer / label', 'type' => 'text', 'ops' => ['contains', 'not_contains']],
    ],

    'operators' => [
        'gte' => 'is at least', 'lte' => 'is at most', 'gt' => 'is more than', 'lt' => 'is less than', 'eq' => 'is exactly',
        'contains' => 'includes', 'not_contains' => 'doesn\'t include', 'is' => 'is', 'is_not' => 'isn\'t',
        'has' => 'has', 'has_not' => 'doesn\'t have', 'in' => 'is one of', 'not_in' => 'isn\'t one of',
    ],

    'actions' => [
        'send_email' => ['label' => 'Send email', 'group' => 'Messages', 'params' => [
            'subject' => ['type' => 'text', 'label' => 'Subject', 'required' => true, 'max' => 200],
            'body' => ['type' => 'textarea', 'label' => 'Message', 'required' => true, 'max' => 5000],
        ], 'note' => 'Emails are prepared and kept in Automation → Emails. Sending starts once an email provider is connected.'],
        'notify' => ['label' => 'Notify your team', 'group' => 'Messages', 'params' => [
            'title' => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 200],
            'body' => ['type' => 'textarea', 'label' => 'Details', 'max' => 2000],
        ]],
        'create_task' => ['label' => 'Create a task', 'group' => 'Messages', 'params' => [
            'title' => ['type' => 'text', 'label' => 'Task', 'required' => true, 'max' => 200],
            'body' => ['type' => 'textarea', 'label' => 'Details', 'max' => 2000],
            'due_days' => ['type' => 'number', 'label' => 'Due in (days)', 'min' => 0, 'max' => 365, 'default' => 1],
        ]],
        'add_order_tag' => ['label' => 'Add order tags', 'group' => 'Tags', 'params' => ['tags' => ['type' => 'text', 'label' => 'Tags', 'required' => true, 'help' => 'Separate with commas.']]],
        'remove_order_tag' => ['label' => 'Remove order tags', 'group' => 'Tags', 'params' => ['tags' => ['type' => 'text', 'label' => 'Tags', 'required' => true]]],
        'add_customer_tag' => ['label' => 'Add customer tags', 'group' => 'Tags', 'params' => ['tags' => ['type' => 'text', 'label' => 'Tags', 'required' => true]]],
        'remove_customer_tag' => ['label' => 'Remove customer tags', 'group' => 'Tags', 'params' => ['tags' => ['type' => 'text', 'label' => 'Tags', 'required' => true]]],
        'create_discount' => ['label' => 'Create a discount code', 'group' => 'Offers', 'params' => [
            'kind' => ['type' => 'select', 'label' => 'Discount', 'options' => ['percent' => '% off', 'amount' => 'Amount off'], 'default' => 'percent'],
            'value' => ['type' => 'number', 'label' => 'Value', 'required' => true, 'min' => 1, 'max' => 10000, 'default' => 10],
            'expires_days' => ['type' => 'number', 'label' => 'Expires after (days)', 'min' => 1, 'max' => 365, 'default' => 30],
            'prefix' => ['type' => 'text', 'label' => 'Code prefix', 'max' => 12, 'default' => 'THANKS'],
        ], 'note' => 'A unique, single-use code. Use {{discount_code}} in a later email.'],
        'webhook' => ['label' => 'Send to a webhook', 'group' => 'Connect', 'params' => [
            'url' => ['type' => 'text', 'label' => 'URL (https)', 'required' => true, 'max' => 500],
        ], 'note' => 'Posts the order details as JSON, for Klaviyo, Zapier, Shopify Flow and others.'],
        'trigger_workflow' => ['label' => 'Start another workflow', 'group' => 'Flow', 'params' => [
            'workflow' => ['type' => 'workflow', 'label' => 'Workflow', 'required' => true],
        ]],
    ],

    'wait_units' => ['minutes' => 'minutes', 'hours' => 'hours', 'days' => 'days'],

];
