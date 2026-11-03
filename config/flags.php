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
        'new_menu' => ['New menu and names', 'The 9-item menu with the new module names.', 'App', 'Phase 2', false],
        'flow_editor' => ['Flow-style automation editor', 'The canvas editor for workflows (same saved format).', 'Automation', 'Phase 2', true],
        'live_chat' => ['Live chat with Growvia support', 'The chat button on every page for the stores you choose. The team console is always on.', 'Support', 'Phase 2', true],
        'premium_plan' => ['Premium plan', 'The Premium plan above Scale and new-module limits.', 'Billing', 'Phase 2', false],
        'profit' => ['Profit module', 'Profit dashboard, costs, ad expenses, breakdowns, ROAS and POAS.', 'Profit', 'Phase 3', false],
        'sales_insights' => ['Sales insights and reports', 'Sales page, weekly email and Monthly Business Report.', 'Insights', 'Phase 3', false],
        'protect' => ['Protect module', 'Alerts, stock, product data, URL monitor, Health Score, Action Center.', 'Protect', 'Phase 4', false],
        'ab_every_offer' => ['A/B tests for every offer', 'Bundle tests where versions change prices, gifts, products, layout, text or design; checkout charges each version\'s price. Gifts, post-purchase and account blocks follow.', 'Offers', 'Phase 2', true],
        'tiered_gifts' => ['Tiered gift bundle upgrade', 'Sale header, open tiers with what\'s inside, gifts row, free shipping reward, delivery bar and price in the button for gift bundles, plus the Berry and Sage models.', 'Offers', 'Phase 2', true],
        'design_tab' => ['Design tab for every offer', 'Element colours, text and layout per screen size for all offers.', 'Offers', 'Phase 5', false],
        'mix_match_tiers' => ['Mix and match quantity tiers', 'Buy X get Y, % off and set price tiers with price per unit: its own card on the bundle page.', 'Offers', 'Phase 2', true],
        'product_documents' => ['Product documents in emails', 'Certificates and PDFs attached by product.', 'Automation', 'Phase 5', false],
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
