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
        'protect' => ['Protect module', 'Money-leak alerts (checked hourly, emailed once a day), inventory and days of stock with a reorder list, and product data gaps. Needs the profit flag (it reads the synced orders and products). URL monitor, Health Score and Action Center have their own flags.', 'Protect', 'Phase 4', true],
        'ai_offer_ideas' => ['AI offer ideas', 'Offers › Offer ideas: products customers buy in the same order (90 days, no AI) as bundle ideas, with offer text on request; and Suggest text in the offer builder\'s Content step (3 options; the merchant picks and edits, nothing saves until they save). The text is written by Growvia from the offer (no AI) by default; with AI switched on in Internal Admin › AI providers, AI writes it for Premium stores within the monthly AI budget, falling back to Growvia\'s own text.', 'Offers', 'Phase 5', true],
        'morning_brief' => ['AI Morning Brief', 'Insights › Morning Brief: every morning (from 6 am store time) up to 5 lines on yesterday against the usual, what changed and the one thing to do today; every line links to the fact it uses; nothing notable means no brief. Written by Growvia from the numbers (no AI) by default; with AI switched on in Internal Admin › AI providers, AI writes it for Premium stores within the monthly AI budget, falling back to Growvia\'s own lines. Needs the profit flag.', 'Insights', 'Phase 5', true],
        'reorder_timing' => ['Predictive reorder timing', 'Automation: a wait in days can wait for each product\'s usual reorder time instead, learned from customers\' repeat orders (median gap, at least 5 repeats); with too little history it uses the fixed wait. Premium. Needs the profit flag (it reads the synced orders).', 'Automation', 'Phase 5', true],
        'translations' => ['Widget translations', 'Offers › Translations: every text in the live storefront offers, per store language, with the original next to it; shoppers see their store language and the default when a text isn\'t translated (or was changed since). Published as one copy per language, so switching language needs no republish. Needs the optional Store languages access. Growth and up.', 'Offers', 'Phase 5', true],
        'store_questions' => ['Store questions', 'Insights › Store questions: over 1,000 ready questions in 12 topics (sales, profit, orders, customers, products, countries and channels, discounts and refunds, costs, stock, product data, Growvia offers, alerts) with search; one click answers from the store\'s own data with a sentence, the numbers and the table (no AI). Favourites and recently asked; Free answers 10 a month. More questions can be added in Internal Admin › Store questions. Needs the profit flag.', 'Insights', 'Phase 5', true],
        'customer_value' => ['Customer value insights', 'Insights › Customers: repeat rate, typical time to a second order, net sales per customer over 12 months, the share of each month\'s new customers ordering again in each of the next 6 months, and the first products whose buyers come back most. Order data only, no names. Scale and up. Needs the profit flag.', 'Insights', 'Phase 5', true],
        'profit_goals' => ['Profit goals and pacing', 'Profit › Goals: a monthly goal for profit after ads, contribution or margin, with so far, projected month-end, what\'s needed per day and a pacing chart; emailed once a month (from day 7) when the projection falls behind by the chosen 10, 15 or 20%. Scale and up. Needs the profit flag.', 'Profit', 'Phase 5', true],
        'monthly_report' => ['Monthly Business Report', 'Insights › Monthly report (last month by default, any of the last 12): sales, profit, margin and orders against the month before, what Growvia\'s offers earned, top products by profit, open problems and 3 things to do, with Email to me and Download PDF. Emailed with the PDF on the 1st to people who can see profit (Starter and up). Needs the profit flag.', 'Insights', 'Phase 4', true],
        'protect_center' => ['Health Score and Action Center', 'Protect › Action Center (open alerts and suggestions sorted by money at risk, with Fix, Snooze and Done) and Protect › Health Score (0–100 from profit setup, stock, product data, pages and open alerts, with an 8-week trend and what raises it most). Needs the protect flag.', 'Protect', 'Phase 4', true],
        'url_monitor' => ['URL monitor', 'Protect › URL monitor: the home page, best sellers, recent collections and the merchant\'s own pages checked every hour (Growth), 15 minutes (Scale) or 5 minutes (Premium); two failed checks in a row raise a Page down alert, the next good check closes it. Needs the protect flag for alerts.', 'Protect', 'Phase 4', true],
        'ab_every_offer' => ['A/B tests for every offer', 'Tests for bundles, gift campaigns, the after-payment offer and account blocks, where versions change prices, rewards, products, layout, text or design; checkout charges each version\'s price.', 'Offers', 'Phase 2', true],
        'tiered_gifts' => ['Tiered gift bundle upgrade', 'Sale header, open tiers with what\'s inside, gifts row, free shipping reward, delivery bar and price in the button for gift bundles, plus the Berry and Sage models.', 'Offers', 'Phase 2', true],
        'design_tab' => ['Design tab for every offer', 'A colour for each part of every storefront offer, text and layout (font, size, weight, spacing, padding, corners, items per row) with desktop, tablet and mobile values, Use brand colours and Copy design from another offer.', 'Offers', 'Phase 5', true],
        'mix_match_tiers' => ['Mix and match quantity tiers', 'Buy X get Y, % off and set price tiers with price per unit: its own card on the bundle page.', 'Offers', 'Phase 2', true],
        'product_documents' => ['Product documents in emails', 'Automation › Product documents: certificates made for each order (serial, verify QR) or the same PDF for everyone, sent by product with the Send email step as attachments or 30-day links, and listed in the customer account My orders block.', 'Automation', 'Phase 5', true],
        'review_prompt' => ['Review request after a milestone', 'Home shows a milestone card (a widget live a week that earned revenue, or the first \$1,000 from offers) and then asks Shopify for its review window (shopify.reviews.request). Shopify decides whether it shows; Growvia waits 60 days between asks.', 'Growth', 'Phase 2', true],
        'lifecycle_emails' => ['Lifecycle emails to store owners', 'Welcome, setup tips (no widget yet, widget not placed), an upgrade note for drafts the plan can\'t publish, and monthly results. Each sent once while its step is undone; owners can turn tips off.', 'Growth', 'Phase 2', true],
        'review_apps' => ['Review star ratings (Judge.me, Yotpo, Loox)', 'Step 1: each offer product\'s real average rating and review count, read from what the review app saves on the product in Shopify (reviews.rating / reviews.rating_count, or Loox\'s fields), shown as stars on bundle tiers and product and cart upsells; refreshed daily; nothing can be typed in. Starter and up. Full review text (step 2) comes with phase 6.', 'Integrations', 'Phase 5', true],
        'integrations' => ['Connected apps (GA4, Klaviyo)', 'Settings › Integrations › Connected apps. GA4: offer views, adds from an offer, purchases with an offer and A/B test versions sent with the shopper\'s GA client id (web pixel, analytics consent only). Klaviyo: Growvia events as metrics for signed-in customers (email read from Shopify on the server) and chosen segments synced daily to lists, email-marketing subscribers only. Send test, event switches, activity log, disconnect (keys deleted). Growth and up.', 'Integrations', 'Phase 6', true],
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
