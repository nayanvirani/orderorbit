<?php

/*
 * The first Growvia blog articles. They seed the blog_posts table once; from then on every post is
 * written, edited and published in Internal Admin → Blog. Bodies are Markdown.
 */

return [
    [
        'slug' => 'what-is-shopify-cro',
        'title' => 'What Is Shopify CRO? A Practical Guide for Growing Brands',
        'category' => 'CRO',
        'feature' => 'analytics',
        'excerpt' => 'What conversion rate optimisation means for a Shopify store, where to start and how to measure it honestly.',
        'seo_title' => 'What Is Shopify CRO? A Practical Guide for Growing Brands',
        'seo_description' => 'Conversion rate optimisation for Shopify, explained: the three numbers that matter, where to start, and how to measure changes without fooling yourself.',
        'body' => <<<'MD'
Conversion rate optimisation (CRO) is the work of getting more value from the visitors you already have. You don't buy more traffic. You make the store better at turning the traffic you have into orders, and orders into bigger orders.

For a Shopify brand that matters because ads keep getting more expensive. If you can get an extra 10% out of every visitor, every channel you pay for gets 10% cheaper.

## The three numbers that matter

Revenue on a Shopify store comes down to one simple formula:

**Revenue = Sessions × Conversion rate × Average order value**

CRO works on the last two:

- **Conversion rate**: the share of sessions that end in an order. Most stores sit somewhere between 1% and 4%, depending on price point, product type and traffic mix.
- **Average order value (AOV)**: what an average order is worth. Bundles, quantity breaks, free-gift thresholds and upsells all push this number.
- **Repeat rate**: often forgotten, but a customer who orders twice is worth far more than two first orders. Thank You pages, reorder buttons and win-back emails work here.

A good CRO plan picks one of these numbers at a time, rather than "improving the store" in general.

## Where to start

Start where shoppers are already deciding. The product page and the cart see the most intent, so small changes there move the most money.

1. **Product page.** Is the price clear? Is there a reason to buy more than one, such as a quantity break or a bundle? Are reviews and guarantees close to the buy button, where the doubt happens?
2. **Cart.** Does the cart show how close the shopper is to free shipping or a free gift? Is there one relevant add-on, not five?
3. **Checkout and Thank You page.** Trust badges, delivery dates and a clear returns line reduce last-second drop-off. After the order, the Thank You page is the best moment to ask for the next one.

## Fix the leaks before adding features

Before you add any widget, check the basics. They often matter more than any app:

- Mobile speed. Most Shopify traffic is mobile, and a slow product page loses shoppers before they see an offer.
- Shipping costs. Surprise shipping at checkout is the most common reason carts are abandoned. Show the cost, or the free-shipping threshold, early.
- Out-of-stock variants. A sold-out default variant quietly kills conversion. Default to an available one.

## Measure honestly

Most CRO "wins" disappear because they were never real. A few rules keep you honest:

- **Compare like with like.** Look at the same weekdays and the same traffic mix. A Black Friday week is not a baseline.
- **Look at revenue per visitor, not just conversion rate.** A discount can lift conversion and still lose money.
- **Give changes time.** One good day proves nothing. Most stores need two to four weeks of data before a difference is trustworthy.
- **Test when you can.** An A/B test splits traffic between two versions at the same time, so seasonality and ad changes affect both equally.

## How Growvia helps

Growvia puts the common CRO tools in one app (bundles, progressive gifts, cart upsells, checkout blocks and more) and reports the revenue each one actually adds. The analytics separate lines added by an offer from the rest of the order, so you see what the widget earned, not the whole basket.

Start with one change on your best-selling product page, measure it for two weeks, and only then add the next one. CRO compounds: five small, measured wins beat one big redesign.
MD,
    ],
    [
        'slug' => 'how-to-create-product-bundles-on-shopify',
        'title' => 'How to Create Product Bundles on Shopify',
        'category' => 'AOV',
        'feature' => 'bundles',
        'excerpt' => 'Mix & match, tiered and routine bundles, and how to keep them compatible with your theme and cart.',
        'seo_title' => 'How to Create Product Bundles on Shopify (Step by Step)',
        'seo_description' => 'The main types of Shopify bundles, which one fits your products, how to price them, and how to set them up so the discount works at checkout.',
        'body' => <<<'MD'
Bundles are one of the most reliable ways to raise average order value. A shopper who came for one product leaves with two or three, and feels they got a better deal doing it.

This guide covers the main bundle types, how to choose between them, and how to set one up so it works with your theme and your checkout.

## The main bundle types

**Quantity breaks.** More of the same product for a lower price per unit: "Buy 2, save 10%. Buy 3, save 15%." Best for consumables and anything people use up.

**Fixed bundles.** A set of specific products sold together: a skincare routine, a camera with a memory card, a "starter kit". Best when products are clearly used together.

**Mix & match.** The shopper picks any 3 (or 5, or 6) from a collection, and the price drops as the bundle fills. Best for ranges with lots of flavours, scents or colours.

**Build your own box.** A bigger version of mix & match: the shopper fills a box with quantities of their choice, within a minimum and maximum size. Great for snacks, coffee, candles and gift boxes.

**Subscription bundles.** Any of the above, delivered on a schedule with a subscribe-and-save price.

## Choosing the right one

Ask two questions:

1. **Do shoppers buy more than one of the same product?** If yes, start with quantity breaks.
2. **Do shoppers buy several different products together?** If yes, a fixed bundle (when the combination is obvious) or mix & match (when taste varies) will work better.

If you're not sure, look at your last 100 multi-item orders. The combinations that already happen are your best bundles.

## Pricing a bundle

Keep the discount simple and visible:

- **Percentage off** works for most bundles, and is easy to understand at every size.
- **Fixed bundle price** ("Any 6 for $60") is strong for boxes and gift sets.
- **Free gift with the bundle** protects your margin better than a deep discount, especially when the gift costs you little.

Show the saving in money, not just percent ("You save $8.40"), and show it on the button. Shoppers respond to the number they actually keep.

## Making it work at checkout

This is where many bundle apps fall over. A bundle is only as good as the price the shopper sees at checkout. Look for:

- **Real discounts at checkout.** Growvia applies bundle pricing through Shopify's own discount and cart-transform functions, so the price is right in the cart, at checkout and on the order.
- **Inventory that stays correct.** Each product in a bundle should still deduct its own stock.
- **No duplicate products.** Bundles shouldn't need you to create a separate "bundle product" that you then have to keep in sync.
- **Theme compatibility.** The bundle should replace or sit next to your theme's add-to-cart without fighting it. Growvia can hide the theme's own quantity and button where a bundle shows, so shoppers see one clear choice.

## Setting up a bundle in Growvia

1. Open **Bundles** and choose a bundle type.
2. Pick a model (the layout) that matches your theme. Every model is fully editable.
3. Choose the products, the discount for each offer and which offer is selected by default.
4. Set where it shows: all products, chosen products or a collection.
5. Publish, then add the Growvia block in the Theme Editor if your theme needs it.

## Tips that move the number

- **Preselect the middle offer.** "Most popular" on the 2-pack nudges shoppers up without feeling pushy.
- **Use real product photos** in fixed bundles, so shoppers see exactly what they get.
- **Keep it to three offers.** More choices slow people down.
- **Watch the bundle's revenue, not just its clicks.** A bundle that converts well but cuts your margin isn't a win.

Start with your best seller, run the bundle for two weeks, and compare revenue per visitor before and after.
MD,
    ],
    [
        'slug' => 'free-gift-with-purchase-thresholds',
        'title' => 'Free Gift with Purchase: How to Set Thresholds That Raise AOV',
        'category' => 'AOV',
        'feature' => 'progressive-gifts',
        'excerpt' => 'Pick thresholds from your own order data, and show shoppers how close they are.',
        'seo_title' => 'Free Gift with Purchase on Shopify: How to Set the Right Threshold',
        'seo_description' => 'How to choose free-gift thresholds from your own order data, pick gifts that protect your margin, and show shoppers how close they are.',
        'body' => <<<'MD'
"Spend $75, get a free gift" works because it gives shoppers a reason to add one more item. But the threshold makes or breaks it. Set it too low and you give gifts to orders that would have happened anyway. Set it too high and nobody bothers.

Here's how to set thresholds from your own data.

## Start from your average order value

Look at your AOV for the last 90 days, excluding big sale events. Then:

- **First threshold:** about 15–30% above your AOV. If your AOV is $60, try $70–$78.
- **Second threshold (optional):** roughly one more typical product above the first. This is where you can offer a better gift or free express shipping.

The goal is a step most shoppers can reach with one more item. Look at your cheapest popular product: the gap between AOV and the first threshold should be close to its price.

## Check the order distribution

AOV hides a lot. Export your recent orders and look at how many land in each $10 band. If lots of orders cluster just below a number (say $55–$65), a threshold at $70 catches them. If orders are spread evenly, the exact number matters less than how clearly you show progress.

## Pick a gift that costs little and feels generous

The best gifts have a high perceived value and a low cost to you:

- Travel sizes and minis of your best sellers (they also work as samples).
- Accessories: a pouch, a scoop, a cleaning cloth.
- Limited items shoppers can't buy separately.

Avoid gifting your hero product at full size. It trains shoppers to wait for the deal.

## Show progress everywhere

A threshold nobody knows about does nothing. Show it:

- On the product page, near the price.
- In the cart, with a progress bar: "Add $12.40 more to unlock your free gift."
- At checkout, so shoppers who are close can go back.

Progress messages work because they turn a vague promise into a small, specific task.

## Tiers: more than one reward

Progressive rewards ("$50 free shipping, $75 free gift, $100 free gift + 10% off") give every shopper a next step. Keep them few and clear: two or three tiers is plenty. Each tier should feel worth the jump.

## Make sure the gift is really free

The gift must come off at checkout without a code, and it must disappear if the shopper drops below the threshold. Otherwise you'll either annoy customers or give away gifts by mistake. Growvia's Progressive gifts add the gift to the cart when it's unlocked and make it free with Shopify's discount function, so checkout always matches the cart.

## Measure it

Track three things for two to four weeks:

1. **AOV** compared with the same period before.
2. **The share of orders that reach each threshold.** If almost nobody reaches it, it's too high. If almost everyone does, it's too low.
3. **Margin after the gift cost.** A higher AOV that loses money isn't a win.

Adjust one threshold at a time. Small moves, around $5–$10, are easier to read than big jumps.
MD,
    ],
    [
        'slug' => 'free-shipping-progress-bar-shopify',
        'title' => 'How to Add a Free Shipping Progress Bar to Shopify',
        'category' => 'CRO',
        'feature' => 'progressive-gifts',
        'excerpt' => 'Why a moving progress bar beats a static banner, and where to place it.',
        'seo_title' => 'How to Add a Free Shipping Progress Bar to Shopify',
        'seo_description' => 'Why a live free-shipping progress bar beats a static banner, where to place it on a Shopify store, and how to set the threshold.',
        'body' => <<<'MD'
Unexpected shipping costs are one of the most common reasons shoppers abandon a cart. A free-shipping threshold turns that cost into a goal, and a progress bar shows shoppers exactly how far away it is.

## Why a progress bar beats a banner

A static "Free shipping over $50" banner is easy to ignore. A bar that says **"You're $8.50 away from free shipping"** is personal and specific. It updates as the cart changes, and it tells the shopper what to do next.

It also removes doubt. Shoppers who already qualify see "You've unlocked free shipping", which is one less reason to hesitate at checkout.

## Where to put it

- **Cart page and cart drawer.** The most important place: shoppers are reviewing their order and deciding whether to add more.
- **Product page.** Near the price or the add-to-cart button, so the threshold is in mind when choosing a quantity.
- **Checkout.** A shipping progress block in checkout catches shoppers who are close and reminds them they can go back.

Avoid putting it everywhere at once on a small screen. On mobile, one clear bar beats three.

## Setting the threshold

Free shipping has a real cost, so set the threshold where it pays for itself:

1. Start about 20–30% above your average order value.
2. Check what shipping actually costs you per order at that size.
3. Make sure the extra margin from the bigger order covers it.

If you already offer free shipping at a set amount, keep that number and just make it visible.

## Pair it with a next step

When the shopper reaches free shipping, the bar shouldn't just stop. Give them the next reward: "Free shipping unlocked. Add $20 more for a free gift." Growvia's Progressive gifts combine shipping, gifts and discounts in one bar, so there's always a next step without three separate widgets.

## Show product suggestions near the bar

A shopper who is $8 away needs something that costs about $8. Showing a few low-priced add-ons next to the bar (a refill, an accessory, a mini) makes the threshold easy to reach. Keep it to two or three products that fit the gap.

## Set it up in Growvia

1. Open **Progressive gifts** and pick a layout: a classic bar, steps, or reward cards.
2. Add a milestone with **Free shipping** as the reward and set the amount.
3. Optionally add a gift or discount at a higher amount.
4. Choose where it shows: cart, product pages or both, and add the checkout block if you want it in checkout.

## Measure

Compare the share of orders that reach the threshold, AOV and conversion rate for two to four weeks before and after. If AOV rises but conversion drops, the threshold may be too high. Lower it a little and test again.
MD,
    ],
    [
        'slug' => 'quantity-breaks-vs-bundles',
        'title' => 'Quantity Breaks vs Bundles: Which Raises AOV More?',
        'category' => 'AOV',
        'feature' => 'bundles',
        'excerpt' => 'When to sell more of one product and when to sell more products.',
        'seo_title' => 'Quantity Breaks vs Bundles: Which Raises AOV More on Shopify?',
        'seo_description' => 'When quantity breaks beat product bundles, when bundles win, and how to test both on your Shopify store.',
        'body' => <<<'MD'
Both quantity breaks and bundles raise average order value. They do it in different ways, and the right choice depends on how your customers already shop.

## Quantity breaks: more of the same

A quantity break lowers the unit price as shoppers buy more: "1 for $29, 2 for $52, 3 for $70."

**Works best when:**

- The product gets used up: supplements, coffee, skincare, pet food, cleaning products.
- Customers already reorder the same item.
- There's one hero product that drives most of your sales.

**Why it works:** shoppers who were going to buy again anyway stock up now. It's easy to understand: one product, three choices, a clear saving.

**Watch out for:** pulling future orders forward. If customers buy three now and nothing for three months, your revenue over the year may not change. Check repeat purchase timing after launching.

## Bundles: more different products

A bundle sells several different products together: a fixed set, a mix & match, or a build-your-own box.

**Works best when:**

- Products are used together: a routine, a kit, an outfit.
- You have a wide range with variety (flavours, scents, colours).
- You want customers to discover products they haven't tried.

**Why it works:** shoppers see the complete solution, and try products they wouldn't have picked alone. That second product can become their next reorder.

**Watch out for:** too much choice. A mix & match with 60 products and no guidance slows people down. Curate the pool or show a few recommended combinations.

## Which raises AOV more?

It depends on your catalogue, not on the tactic:

| Your store | Start with |
| --- | --- |
| One hero product, frequent reorders | Quantity breaks |
| A range used together | Fixed bundles |
| Lots of variants or flavours | Mix & match or build your own box |
| Gifting is a big part of sales | Fixed bundles with a gift |

Many stores end up using both: quantity breaks on the hero product, bundles on collection pages and gift sets.

## Combining them

You can combine the two in one widget: offer "1 bottle", "2 bottles −10%", and a "Complete routine −15%" bundle side by side. Shoppers compare their options in one place instead of hunting for deals. In Growvia, a single bundle widget can mix quantity offers and product packs, and each one can carry its own free gift.

## Test it

Don't guess. Run quantity breaks on one product page and a bundle on a similar one, or A/B test the two layouts on the same product. Compare **revenue per visitor**, not conversion rate alone, because a deeper discount can lift conversion while lowering revenue.

Give each test at least two weeks and a few hundred orders before deciding.
MD,
    ],
    [
        'slug' => 'shopify-upsell-cross-sell-ideas',
        'title' => 'Shopify Upsell and Cross-sell Ideas That Don\'t Annoy Shoppers',
        'category' => 'AOV',
        'feature' => 'cart-upsells',
        'excerpt' => 'Recommendations placed where shoppers are already deciding, with no forced drawers or surprise pop-ups.',
        'seo_title' => 'Shopify Upsell & Cross-sell Ideas That Don\'t Annoy Shoppers',
        'seo_description' => 'Upsell and cross-sell ideas for Shopify that raise order value without pop-ups or pressure: what to recommend, where, and how many.',
        'body' => <<<'MD'
Upsells get a bad reputation from pop-ups that block the page and carts that refuse to close. Done well, a recommendation is a service: it helps the shopper finish the job they came to do.

## Upsell or cross-sell?

- **Upsell:** a better or bigger version of what they're buying. The 100 ml instead of the 50 ml, the bundle instead of the single item.
- **Cross-sell:** something that goes with it. A case for the phone, a refill for the starter kit, socks for the shoes.

Cross-sells usually feel more natural, because they add to the order without questioning the shopper's choice.

## Rules for recommendations that convert

1. **Relevant first.** "Frequently bought together" beats "You might also like". Use what your customers actually buy together.
2. **Cheaper than the main item.** An add-on should be an easy yes. As a rule of thumb, aim for well under half the price of what's in the cart.
3. **Few choices.** Two or three products, not a carousel of twelve.
4. **One click.** Add to cart without leaving the page or picking a variant in a separate window, where you can.
5. **No forced steps.** Never block checkout with a pop-up the shopper has to dismiss.

## Where to show them

**Product page.** Below the add-to-cart button: "Complete the look" or "Pairs well with". Shoppers are still deciding, so this is the place for complementary items.

**Cart.** The strongest spot for small add-ons, especially when paired with a free-shipping or gift threshold: "Add one of these to unlock free shipping."

**Checkout.** A single, simple add-on, such as protection, a refill or a sample, can work in checkout, where shoppers have already committed.

**After the order.** The Thank You page and post-purchase offers are ideal for "buy again" and "you'll also need" products. The sale is done, so a recommendation can't hurt it.

## Ideas by store type

- **Beauty:** the matching cleanser or moisturiser, a travel size, a refill.
- **Coffee:** a filter pack, a grinder, a second roast at a bundle price.
- **Fashion:** socks, belts, care products. Small items that complete the outfit.
- **Pet:** treats and toys next to food, a second bag at a better price.
- **Electronics:** cases, cables and protection plans.

## Add a small incentive

A modest discount on add-ons bought with the main item ("10% off when added to this order") gives a reason to decide now. Keep it to the add-ons only, so your main product's price stays intact. Growvia's cart upsells can apply a discount only to items added from the offer, and the saving appears at checkout automatically.

## Measure

Look at the share of orders containing an upsold item, and the revenue those items add. If an upsell gets lots of clicks but few orders, the product or the price is wrong. Change one thing at a time.
MD,
    ],
    [
        'slug' => 'shopify-checkout-customisation',
        'title' => 'What You Can (and Can\'t) Customise in Shopify Checkout',
        'category' => 'Checkout',
        'feature' => 'checkout',
        'excerpt' => 'What Shopify lets you add to checkout, plan limits, and what Thank You and Order Status blocks can do on every plan.',
        'seo_title' => 'What You Can and Can\'t Customise in Shopify Checkout',
        'seo_description' => 'A plain-English guide to Shopify checkout customisation: what every plan can change, what needs Shopify Plus, and what apps can add.',
        'body' => <<<'MD'
Shopify's checkout is fast and trusted, and that's partly because you can't change everything about it. Knowing exactly what you *can* change saves a lot of time and stops you paying for tools you can't use.

## What every plan can change

**Branding.** In the checkout editor you can set your logo, colours, fonts and some layout options, so checkout looks like your store.

**Thank You and Order Status pages.** Every plan can add app blocks to these pages: reorder buttons, cross-sells, surveys, discount codes for the next order, delivery information and images. These pages are seen by every customer and are often overlooked.

**Shipping, payment and discount settings.** Shipping rates, payment methods and automatic discounts are all set in Shopify admin and show at checkout. Apps can also create discounts through Shopify Functions, so bundle and gift pricing appears correctly at checkout.

## What needs Shopify Plus

**Blocks inside the checkout steps.** Adding app blocks to the information, shipping and payment steps (a trust row, a delivery estimate, an upsell, a gift message) uses checkout UI extensions, which Shopify makes available on Shopify Plus.

**Some advanced logic.** Hiding, reordering or renaming payment and delivery methods, and validating the cart before payment, use Shopify Functions that are, for the most part, Plus features too.

## What no one can change

Some parts of checkout are off-limits to everyone, including apps:

- The core payment form and the order of the main steps.
- Arbitrary code or scripts on the checkout page.
- Changing the price of an item in checkout without a discount or a cart transform.

This is deliberate. It keeps checkout secure and fast for every store.

## The old checkout.liquid

If you're on Shopify Plus and still have customisations in `checkout.liquid`, Shopify has replaced that approach with checkout extensibility (UI extensions, Functions and the checkout editor). Anything you built in `checkout.liquid` needs to be rebuilt with blocks and apps.

## What's worth adding

On any plan:

1. **A Thank You page cross-sell or reorder block.** The order is done, the customer is happy, and the next purchase is one click away.
2. **A post-purchase survey.** "How did you hear about us?" gives you attribution data no ad platform can.
3. **A next-order discount.** A code shown on the Thank You page brings customers back sooner.

On Shopify Plus, also consider:

4. **A trust row** (secure payment, returns, delivery) near the payment step.
5. **A shipping progress bar** for shoppers just under your free-shipping threshold.
6. **One simple add-on**, such as protection or a sample. Keep it to one.

## How Growvia handles checkout

Growvia's checkout blocks include reviews, countdowns, shipping progress, free gifts, promotions, trust badges, upsells and images. Blocks for the Thank You and Order Status pages work on every plan. Blocks inside the checkout steps show on Shopify Plus, and the app tells you which is which, so you don't build something your plan can't show.

Bundle and gift pricing doesn't need Plus at all: it's applied through Shopify's discount functions on every plan.
MD,
    ],
    [
        'slug' => 'shopify-thank-you-page-repeat-orders',
        'title' => 'How to Use the Shopify Thank You Page to Drive Repeat Orders',
        'category' => 'Retention',
        'feature' => 'checkout',
        'excerpt' => 'Turn the confirmation page from a dead end into your next sale.',
        'seo_title' => 'How to Use the Shopify Thank You Page to Get Repeat Orders',
        'seo_description' => 'Practical ways to turn the Shopify Thank You and Order Status pages into repeat orders: reorder buttons, next-order offers, cross-sells and surveys.',
        'body' => <<<'MD'
The Thank You page is the one page every customer sees, at the moment they're happiest with your store. On most stores it's a dead end: an order number and a "Continue shopping" link.

It doesn't have to be. Since Thank You and Order Status page blocks work on every Shopify plan, any store can turn this page into the start of the next order.

## Why it works

- **Trust is at its highest.** The customer just decided you're worth buying from.
- **Attention is guaranteed.** Nearly everyone sees the page, and many come back to the Order Status page to track their parcel.
- **There's nothing left to lose.** The first order is done. A recommendation can't put it at risk.

## Five blocks worth adding

**1. A next-order discount.** "10% off your next order, valid for 30 days." Give a deadline so it feels real, and show the code clearly so it's easy to copy.

**2. "You'll also need" cross-sells.** Products that complete what they just bought: the refill, the matching item, the accessory. Two or three, not a catalogue.

**3. A reorder button for consumables.** On the Order Status page, a one-click "Order again" is ideal for products people use up.

**4. A one-question survey.** "How did you hear about us?" Answers tell you which channels really drive first orders, something ad dashboards can't.

**5. A referral or review prompt.** Ask for a review later, once the product has arrived. On the Thank You page itself, a referral offer ("Give $10, get $10") works better.

## Keep it calm

The customer came to see that their order went through. Put that first: the confirmation, the delivery estimate and what happens next. Add one or two blocks below, not six. A cluttered Thank You page feels like an upsell machine and undoes the goodwill you just earned.

## Match the block to the product

- **Consumables:** reorder button and subscribe-and-save.
- **Gifts:** a "send another" offer or a gift-card prompt.
- **Fashion:** "complete the look" items in the same size.
- **High-ticket items:** care products, protection or accessories.

## Measure what it brings

Track how many orders use the Thank You page discount code, and the revenue from Thank You page cross-sells. Check repeat purchase rate over 60–90 days, compared with before. Retention changes are slow, so give it time.

## Set it up in Growvia

Open **Thank You & Order Status**, pick a block (cross-sell, reorder, review, referral, survey, discount, message or image), customise it, then add it in Shopify's checkout editor on the Thank You or Order Status page. It works on every Shopify plan.
MD,
    ],
    [
        'slug' => 'how-to-run-ab-test-shopify',
        'title' => 'How to Run a Valid A/B Test on Shopify',
        'category' => 'Testing',
        'feature' => 'ab-testing',
        'excerpt' => 'Sample size, duration, guardrails and why early results lie.',
        'seo_title' => 'How to Run a Valid A/B Test on Shopify',
        'seo_description' => 'How to plan, run and read an A/B test on Shopify: what to test, how long to run it, sample size, and the mistakes that make results meaningless.',
        'body' => <<<'MD'
An A/B test shows two versions of something to different shoppers at the same time, then compares the results. It's the most honest way to know whether a change helped, because both versions face the same traffic, the same ads and the same week.

It's also easy to get wrong. Here's how to run one you can trust.

## 1. Test one clear idea

A good test changes one thing for a reason: "Showing the saving in dollars instead of percent will lift bundle orders." If you change the layout, the copy and the discount at once, you won't know what made the difference.

Good first tests on Shopify:

- Bundle layout: stacked cards vs side by side.
- Which offer is selected by default.
- Free-gift threshold: $70 vs $80.
- Headline wording on an offer.

## 2. Choose your metric before you start

Pick one primary metric and write it down:

- **Revenue per visitor** is usually the best choice. It captures both conversion and order value.
- **Conversion rate** for changes that only affect whether people buy.
- **Average order value** for changes that only affect basket size.

Add a **guardrail**: a metric that must not get worse, like conversion rate when you're testing a higher threshold.

## 3. Get enough data

Small tests produce noise that looks like a result. As a rough guide:

- Aim for **at least a few hundred orders per version** before reading the result. Small differences need much more.
- Run for **at least two full weeks**, so every weekday appears twice.
- Don't stop the test early because one version is "winning". Early leads often disappear.

If your store doesn't get enough traffic for that, test bigger changes. They produce bigger differences, which are easier to detect.

## 4. Keep the split fair

- **Same shopper, same version.** A returning visitor should keep seeing the version they saw first.
- **50/50 split** unless you have a reason not to.
- **Don't change anything else** on the page during the test.

## 5. Read the result properly

When the test ends, look at the difference *and* how sure you can be about it. A tool should tell you the confidence. If it's below about 95%, treat the result as "no clear winner", not as a small win.

No clear winner is still useful: it means you can choose the version you prefer for other reasons, like simplicity or margin.

## Common mistakes

- **Peeking and stopping early.** The most common way to fool yourself.
- **Testing during a sale.** Shopper behaviour during Black Friday isn't normal behaviour.
- **Too many versions.** Each extra version needs more traffic.
- **Ignoring margin.** A version that converts better with a bigger discount may still earn less.

## A/B testing in Growvia

Growvia's experiments split visitors between widget versions and keep each visitor on the same one. They track revenue per visitor, conversion and AOV, with guardrails, and show when a result is reliable enough to act on. When a test ends, you can apply the winner in one click.
MD,
    ],
    [
        'slug' => 'post-purchase-automation-flows',
        'title' => 'Post-Purchase Automation: Review, Reorder and Win-back Flows',
        'category' => 'Retention',
        'feature' => 'automation',
        'excerpt' => 'Three workflows every repeat-purchase brand should run, with timing that fits your product.',
        'seo_title' => 'Post-Purchase Automation for Shopify: Review, Reorder & Win-back',
        'seo_description' => 'Three post-purchase workflows every Shopify brand should run, how to time them for your product, and what to put in each message.',
        'body' => <<<'MD'
Getting a second order is easier than getting a first one, but only if you ask at the right time. Post-purchase automation sends the right message when a customer is ready for it, without you remembering to.

Here are the three flows every repeat-purchase brand should run.

## 1. The review request

**When:** a few days after delivery, not after the order. The customer needs time to use the product.

**What to send:** a short, friendly message with a direct link to leave a review. One question, one button.

**Timing guide:**

- Fast-acting products (snacks, accessories): 3–5 days after delivery.
- Products that take time to judge (skincare, supplements): 2–3 weeks after delivery.

**Bonus:** tag customers who leave a review. They're your happiest customers, and the best audience for a referral offer.

## 2. The reorder reminder

**When:** just before the product typically runs out.

Work out the timing from your own data: look at how long your repeat customers wait between orders of the same product. If a bag of coffee lasts about four weeks, remind them at three.

**What to send:** "Running low?" with a one-click link back to the product, or a reorder link that fills the cart. A small incentive helps, but it isn't always needed. Convenience is the main point.

## 3. The win-back

**When:** after a customer has gone quiet for noticeably longer than usual. If your customers normally reorder every 45 days, a customer who hasn't ordered in 90 is drifting away.

**What to send:** something new or a reason to come back: a new product, a restock of something they bought, or a time-limited offer. Keep the discount for the second or third message, not the first.

## Keep it relevant

- **Use what they bought.** A message about the product they ordered beats a general newsletter.
- **Stop when they buy.** Remove customers from a flow as soon as they place an order, so nobody gets a "We miss you" email the day after ordering.
- **Respect consent.** Only email customers who agreed to marketing, and make unsubscribing easy.

## Measure each flow

For each workflow, track how many customers enter it, how many convert, and the revenue it brings. Compare repeat purchase rate before and after over 60–90 days. If a flow converts poorly, change the timing first, then the message.

## Automation in Growvia

Growvia's workflows start from real store events: an order placed, paid, fulfilled or delivered, a refund, a new customer or a tag added. Add waits and conditions, then tag customers, send emails or create discount codes. Each workflow shows its runs, success rate and results, so you can see what it earns.
MD,
    ],
    [
        'slug' => 'build-your-own-box-shopify',
        'title' => 'Build Your Own Box on Shopify: A Guide for Snack, Coffee and Beauty Brands',
        'category' => 'AOV',
        'feature' => 'bundles',
        'excerpt' => 'Let shoppers fill a box with the products and quantities they want, with a minimum, a maximum and a price that rewards a bigger box.',
        'seo_title' => 'Build Your Own Box on Shopify: Setup, Pricing and Limits',
        'seo_description' => 'How to set up a build-your-own-box bundle on Shopify: choosing products or a collection, box size limits, pricing by tiers or one box price, and subscriptions.',
        'body' => <<<'MD'
A build-your-own box lets shoppers fill a box with exactly what they want: four chocolate bars and two caramel, three dark roasts and one decaf. It feels personal, it raises order value, and it's one of the easiest ways to get people to try more of your range.

## When a box makes sense

Build your own box works best when:

- You sell **many variations** of a similar product: flavours, roasts, scents, colours.
- Products are **small and affordable on their own**, so a box of 6–12 feels natural.
- Customers like to **mix**, or to try something new alongside a favourite.
- **Gifting** is part of your business.

Snacks, coffee and tea, candles, cosmetics minis, socks and pet treats are classic examples.

## Products or a collection?

You can fill a box from **products you pick** or from **a whole collection**.

- **Products you pick** gives full control. Good for a curated box.
- **A collection** keeps itself up to date: add a product to the collection and it appears in the box. Good for large ranges and seasonal products.

## Set the box size

Limits keep the box profitable and the experience clear:

- **Minimum items:** the box can't be added to the cart below this. It protects shipping costs and makes sure the discount is earned.
- **Maximum items:** the box can't go over this. It fits your packaging.
- **Limit per product (optional):** "up to 3 of each" encourages variety and protects stock of your best sellers.

Make the limits visible: "Choose 6 to 12 items. 4 of 12 added." Shoppers should always know where they stand.

## Price the box

Three common approaches:

1. **Discount by number of items.** "6 items save 10%, 12 items save 20%." Rewards bigger boxes.
2. **One price for the box.** "Any 6 for $60." Very easy to understand, and great for gift boxes. This works with an exact box size.
3. **No discount.** The convenience and the choice are the value, often with a free gift at a certain size instead.

## Quantity steppers or add buttons?

With quantity steppers (− 2 +), shoppers can add several of the same product. With simple **Add / Remove** buttons, each product goes in once, which works well for sampler boxes where variety is the point.

## Make it a subscription

Boxes are a natural fit for subscriptions: a monthly coffee box, a snack box every two weeks. Shoppers choose their mix once and receive it on a schedule. Make sure each product in the box has the same subscription plans in your subscription app.

## Make sure checkout respects the box

The box's price should only apply to a box that keeps its rules. A good setup checks the limits again at checkout, so shoppers can't change quantities in the cart to get the box discount on something that isn't a valid box.

## Build your own box in Growvia

1. Open **Bundles** and choose **Build your own box**.
2. Choose products or a collection, and set the minimum, maximum and limit per product.
3. Pick the pricing: discount steps, one box price, or none.
4. Choose quantity steppers or Add / Remove buttons, and whether to show a variant picker.
5. Optionally add a free gift that unlocks at a box size, and turn on subscriptions.

Growvia checks the box size, the product limits and collection membership at checkout, and the box pays only for what's in it.
MD,
    ],
    [
        'slug' => 'subscription-bundles-shopify',
        'title' => 'Subscription Bundles on Shopify: Subscribe and Save on Packs and Boxes',
        'category' => 'Retention',
        'feature' => 'bundles',
        'excerpt' => 'Combine a bundle discount with your subscription app, so shoppers subscribe to a whole routine instead of one product.',
        'seo_title' => 'Subscription Bundles on Shopify: Subscribe & Save on Packs and Boxes',
        'seo_description' => 'How subscription bundles work on Shopify: combining bundle discounts with selling plans, what shoppers see, and how pricing works on the first and later deliveries.',
        'body' => <<<'MD'
Subscriptions bring predictable revenue. Bundles raise order value. A subscription bundle does both: shoppers subscribe to a whole routine, pack or box, not just one product.

## How subscriptions work on Shopify

Shopify subscriptions are built on **selling plans**: delivery frequencies like "every month", each with its own price. Your subscription app (Shopify Subscriptions, Recharge, Skio, Loop, Seal, Appstle and others) creates these plans and handles renewals, billing and the customer portal.

A subscription bundle uses those same plans. Each product in the bundle is added to the cart on its plan for the chosen frequency, so your subscription app manages it like any other subscription.

## What shoppers see

A good subscription bundle shows:

- **One-time or subscribe**, side by side, with the price of each.
- **The delivery frequency** to choose from: every 2 weeks, every month, and so on.
- **What later deliveries cost**, clearly: "Then $52.20 every month."
- **The benefits**: "Skip, pause or cancel anytime."

Showing the exact price of later deliveries matters. It builds trust and reduces cancellations from customers surprised by their second charge.

## How the pricing works

There are two savings in play:

1. **The subscription saving**, set in your subscription app (for example 10% off every delivery).
2. **The bundle saving**, from the bundle offer (for example 15% off the pack).

A common setup is to give the bundle discount on the **first delivery**, and the subscription price on every delivery after that. The first order gets an extra reason to subscribe, and your margin on renewals stays protected.

## Which bundles work as subscriptions

- **Routines:** cleanser, serum and moisturiser delivered monthly.
- **Quantity packs:** "2 bottles every month".
- **Build your own box:** a coffee or snack box the shopper fills once and receives on a schedule.

Every product in the bundle needs a plan for the same frequency, so a monthly bundle needs a monthly plan on each product.

## Tips

- **Preselect subscribe** if most of your revenue is recurring, or one-time if you're still building trust with new customers.
- **Keep frequencies few.** Two or three options are enough.
- **Show the saving as a badge:** "Save 10%".
- **Make cancelling easy** in your subscription app. Customers subscribe more readily when leaving is simple.

## Subscription bundles in Growvia

Turn on **Subscriptions** in any bundle. Growvia reads the selling plans from your subscription app, shows only the frequencies every product in the offer shares, and adds each product on its plan. You can choose a layout (two option cards, a toggle or a checkbox), the default choice and the wording. The bundle discount applies to the first delivery, and later deliveries are priced by your subscription plan, exactly as shown to the shopper.
MD,
    ],
];
