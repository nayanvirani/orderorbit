<?php

/*
|--------------------------------------------------------------------------
| Feature flags (G40)
|--------------------------------------------------------------------------
|
| Every flag the code checks. The Internal Admin lists these to choose from;
| keys can't be typed in. "release" flags turn a new module on for chosen
| stores before everyone; "kill" flags are emergency switches that hide a live
| module for every store at once (settings and data are kept).
|
| 'built' => false marks modules from the next MVP that aren't in the app yet:
| their flag can be set up in advance and takes effect when the module ships.
|
*/

return [
    'release' => [
        'new_menu' => ['New menu and names', 'The new menu (Offers, Insights, Automation, Audiences and tests), friendly offer names and Offers › Create an offer. Labels only: routes, keys and saved offers are unchanged. Profit and Protect join the menu when they ship.', 'App', 'Phase 2', true],
        'flow_editor' => ['Flow-style automation editor', 'The canvas editor for workflows (same saved format).', 'Automation', 'Phase 2', true],
        'live_chat' => ['Live chat with Growvia support', 'The chat button on every page for the stores you choose. The team console is always on.', 'Support', 'Phase 2', true],
        'premium_plan' => ['Premium plan and custom plans', 'Shows the Premium plan ($129.99, above Scale) on the plans page and lets owners ask for a custom plan from Settings › Plan and billing. Set up Premium in the Partner Dashboard (managed pricing, name "Premium") before turning it on. New-module limits and Free previews apply without the flag.', 'Billing', 'Phase 2', true],
        'profit' => ['Profit module', 'Profit dashboard, costs and the missing-cost finder, ad expenses, breakdowns and ROAS/POAS from synced orders. Asks each store to approve product costs and order history (optional access) when Profit is opened. Free sees a 30-day snapshot.', 'Profit', 'Phase 3', true],
        'sales_insights' => ['Sales insights and weekly email', 'Insights › Sales (net sales, orders, average order, returning customers, weekly new vs returning, products by profit, countries) and the Monday summary email to people who can see profit. Needs the profit flag too. The Monthly Business Report comes with phase 4.', 'Insights', 'Phase 3', true],
        'protect' => ['Protect module', 'Alerts, stock, product data, URL monitor, Health Score, Action Center.', 'Protect', 'Phase 4', false],
        'ab_every_offer' => ['A/B tests for every offer', 'Tests for bundles, gift campaigns, the after-payment offer and account blocks, where versions change prices, rewards, products, layout, text or design; checkout charges each version\'s price.', 'Offers', 'Phase 2', true],
        'tiered_gifts' => ['Tiered gift bundle upgrade', 'Sale header, open tiers with what\'s inside, gifts row, free shipping reward, delivery bar and price in the button for gift bundles, plus the Berry and Sage models.', 'Offers', 'Phase 2', true],
        'design_tab' => ['Design tab for every offer', 'A colour for each part of every storefront offer, text and layout (font, size, weight, spacing, padding, corners, items per row) with desktop, tablet and mobile values, Use brand colours and Copy design from another offer.', 'Offers', 'Phase 5', true],
        'mix_match_tiers' => ['Mix and match quantity tiers', 'Buy X get Y, % off and set price tiers with price per unit: its own card on the bundle page.', 'Offers', 'Phase 2', true],
        'product_documents' => ['Product documents in emails', 'Automation › Product documents: certificates made for each order (serial, verify QR) or the same PDF for everyone, sent by product with the Send email step as attachments or 30-day links, and listed in the customer account My orders block.', 'Automation', 'Phase 5', true],
        'review_prompt' => ['Review request after a milestone', 'Home shows a milestone card (a widget live a week that earned revenue, or the first \$1,000 from offers) and then asks Shopify for its review window (shopify.reviews.request). Shopify decides whether it shows; Growvia waits 60 days between asks.', 'Growth', 'Phase 2', true],
        'lifecycle_emails' => ['Lifecycle emails to store owners', 'Welcome, setup tips (no widget yet, widget not placed), an upgrade note for drafts the plan can\'t publish, and monthly results. Each sent once while its step is undone; owners can turn tips off.', 'Growth', 'Phase 2', true],
        'review_apps' => ['Review apps (Judge.me, Yotpo, Loox)', 'Real ratings and reviews from review apps.', 'Integrations', 'Phase 5', false],
        'integrations' => ['Klaviyo, GA4 and Gorgias', 'The phase 6 integrations.', 'Integrations', 'Phase 6', false],
    ],
    'kill' => [
        'kill.storefront_widgets' => ['Storefront widgets', 'Bundles, gifts, upsells, timers and every other widget on the online store.'],
        'kill.sales_pop' => ['Sales pop', 'Pop-ups stop on every page.'],
        'kill.checkout_blocks' => ['Checkout and Thank You blocks', 'All Growvia blocks in checkout, Thank You and Order Status.'],
        'kill.customer_accounts' => ['Customer account blocks', 'All Growvia blocks in customer accounts.'],
        'kill.post_purchase' => ['Post-purchase offer', 'The page is skipped; orders complete normally.'],
        'kill.automation_emails' => ['Automation emails', 'Emails wait in the queue and send when turned back on.'],
        'kill.web_pixel' => ['Web pixel events', 'Analytics events are not collected.'],
    ],
];
