@extends('layouts.site')

@section('title', 'A/B Testing Guide | OrderOrbit Space Docs')
@section('description', 'How to set up, run and read A/B and A/B/C tests in OrderOrbit Space: variants, traffic, audience, metrics, guardrails, the statistical method and best practices.')

@php
    $toc = [
        'overview' => 'Overview',
        'what' => 'What you can test',
        'before' => 'Before you start',
        'setup' => 'Set up a test, step by step',
        'checkout' => 'Checkout and Thank You blocks',
        'assignment' => 'How visitors are split',
        'results' => 'Reading the results',
        'method' => 'The statistical method',
        'manage' => 'Pause, stop and apply a winner',
        'practices' => 'Best practices',
        'limits' => 'Limits',
        'faq' => 'Troubleshooting and FAQ',
    ];
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}">
@endpush

@section('content')
<div class="mn docs">
    <section class="mn-hero">
        <div class="wrap">
            <span class="mn-kicker">Docs · A/B testing</span>
            <h1>Run A/B tests <em>you can trust.</em></h1>
            <p class="mn-lead">Everything about A/B and A/B/C testing in OrderOrbit Space: what you can test, each setup step, how visitors are split, how to read results and when a winner is real.</p>
            <p class="docs-meta">Available on the Growth and Scale plans · In the app: <strong>A/B tests</strong> in the left menu</p>
        </div>
    </section>

    <div class="docs-layout wrap">
        <aside class="docs-toc" aria-label="On this page">
            <p class="mn-group">On this page</p>
            <ol>
                @foreach ($toc as $id => $label)<li><a href="#{{ $id }}">{{ $label }}</a></li>@endforeach
            </ol>
        </aside>

        <article class="docs-body">
            {{-- 1 --}}
            <section id="overview">
                <h2>Overview</h2>
                <p>An A/B test shows different versions of one live widget to different visitors at the same time, then compares what those visitors did. Because both groups shop in the same week, with the same traffic and the same prices, the difference between them comes from the change you made, not from the season or a marketing push.</p>
                <p>In OrderOrbit Space a test has a <strong>control (A)</strong>, which is your widget exactly as published, and one or two <strong>variants (B, and optionally C)</strong>. Each visitor is placed in one of them and always sees the same one. When the test has enough days, visitors and conversions, the app tells you whether a variant really did better, and you can apply it to everyone in one click.</p>
                <div class="docs-flow" aria-label="The test loop">
                    <span>Choose a widget</span><span>Create variants</span><span>Split traffic</span><span>Collect data</span><span>Read results</span><span>Apply the winner</span>
                </div>
            </section>

            {{-- 2 --}}
            <section id="what">
                <h2>What you can test</h2>
                <p>Tests run on <strong>published</strong> widgets. A variant can change:</p>
                <ul class="docs-list">
                    <li><strong>Template</strong>: a different layout from the same feature (for example cards instead of a slider).</li>
                    <li><strong>Text</strong>: headlines, messages and button labels.</li>
                    <li><strong>Design</strong>: colours, corners, borders, spacing and the other Design settings. Checkout blocks use Shopify's checkout styles (background, border, corners, width and text tone).</li>
                    <li><strong>Holdout</strong>: hide the widget from that group, to measure what the widget is worth overall.</li>
                </ul>
                <p>Products, prices, discounts and thresholds always stay as published. That keeps checkout honest: every shopper gets the price and offer the widget promised, whichever variant they saw.</p>
                <div class="docs-table">
                    <table>
                        <thead><tr><th>Widget</th><th>Can be tested</th><th>Notes</th></tr></thead>
                        <tbody>
                            <tr><td>Product and cart upsells, countdowns, sticky add to cart, trust badges, sales pop, pre-orders, shipping bar, free gifts and other storefront widgets</td><td class="yes">Yes</td><td>Holdout isn't offered for widgets that apply a discount, so nobody gets a discount for a widget they couldn't see.</td></tr>
                            <tr><td>Checkout blocks (reviews, countdown, shipping progress, free gift, promotion, trust, image)</td><td class="yes">Yes</td><td>Audience: cart value and country. See <a href="#checkout">Checkout and Thank You blocks</a>.</td></tr>
                            <tr><td>Thank You and Order Status blocks</td><td class="yes">Yes</td><td>Judged by click-through rate by default, since the order is already placed.</td></tr>
                            <tr><td>Bundles and Progressive gifts</td><td class="no">Not yet</td><td>Their prices are applied at checkout by Shopify functions.</td></tr>
                            <tr><td>Post-purchase offer</td><td class="no">Not yet</td><td>The offer is chosen by the server for each order.</td></tr>
                            <tr><td>Customer account blocks</td><td class="no">Not yet</td><td></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 3 --}}
            <section id="before">
                <h2>Before you start</h2>
                <ol class="docs-check">
                    <li><strong>Be on Growth or Scale.</strong> You can build a test on any plan, but launching needs Growth or Scale.</li>
                    <li><strong>Publish the widget</strong> you want to test, and make sure it shows on your store (its block is placed in the Theme Editor, or in Shopify's checkout editor for checkout blocks).</li>
                    <li><strong>Check that Analytics is connected</strong> (Analytics in the app). Tests are measured with the OrderOrbit Space web pixel.</li>
                    <li><strong>Make sure there's enough traffic.</strong> A winner needs at least 1,000 visitors and 100 conversions in every variant. See the <a href="#practices">sample size table</a> to estimate how long that takes for your store.</li>
                    <li><strong>Write down one idea to test</strong>, and why you think it will help. Testing one change at a time makes the result easy to act on.</li>
                </ol>
            </section>

            {{-- 4 --}}
            <section id="setup">
                <h2>Set up a test, step by step</h2>
                <p>Open <strong>A/B tests</strong> in the app, choose the widget under <strong>Create a test</strong> and click <strong>Create test</strong>. You can also click <strong>Create A/B test</strong> on any published widget's page. The setup page has nine steps; you can save a draft at any point.</p>

                <div class="docs-step" id="step-1">
                    <span class="docs-num">1</span>
                    <div>
                        <h3>Widget and hypothesis</h3>
                        <p>Give the test a name you'll recognise later, such as "Upsell: slider vs cards". The hypothesis is optional but useful: what you're changing, what you expect to happen and why. For example: <em>"Showing the upsell as a slider will raise add-to-cart rate because more products fit on mobile."</em></p>
                    </div>
                </div>

                <div class="docs-step" id="step-2">
                    <span class="docs-num">2</span>
                    <div>
                        <h3>Variants</h3>
                        <p><strong>A · Control</strong> is the widget exactly as published; it can't be edited here. <strong>Variant B</strong> starts as a copy of the control. Change one or more of:</p>
                        <ul class="docs-list">
                            <li><strong>Template</strong>: pick another layout of the same feature.</li>
                            <li><strong>Text</strong>: open the Text panel and rewrite headlines, messages or buttons. Fields you leave as they are keep the control's text.</li>
                            <li><strong>Design</strong>: open the Design panel and change colours, corners, spacing and so on.</li>
                            <li><strong>Holdout</strong>: tick it to hide the widget from this group instead.</li>
                        </ul>
                        <p>Tick <strong>Add a third variant</strong> for an A/B/C test. Variants must differ from the control; the app won't launch a test where B is identical to A.</p>
                    </div>
                </div>

                <div class="docs-step" id="step-3">
                    <span class="docs-num">3</span>
                    <div>
                        <h3>Traffic allocation</h3>
                        <p>Choose what share of the test's visitors sees each variant. The shares must add up to <strong>100%</strong>; <strong>Split evenly</strong> fills them for you. An even split (50/50 or 34/33/33) reaches a result fastest. Give a risky variant less traffic (for example 80/20) if you want to limit its exposure, knowing the test will take longer.</p>
                    </div>
                </div>

                <div class="docs-step" id="step-4">
                    <span class="docs-num">4</span>
                    <div>
                        <h3>Audience</h3>
                        <p>Choose who takes part. Visitors outside the audience see the widget as published and aren't counted. Leave everything empty to include everyone who sees the widget.</p>
                        <div class="docs-table">
                            <table>
                                <thead><tr><th>Setting</th><th>Storefront</th><th>Checkout and Thank You</th></tr></thead>
                                <tbody>
                                    <tr><td>Device (mobile or desktop)</td><td class="yes">Yes</td><td class="no">No</td></tr>
                                    <tr><td>Countries (two-letter codes, e.g. US, CA)</td><td class="yes">Yes</td><td class="yes">Yes</td></tr>
                                    <tr><td>Products and collections</td><td class="yes">Yes</td><td class="no">No</td></tr>
                                    <tr><td>Cart value at least / at most</td><td class="yes">Yes</td><td class="yes">Yes</td></tr>
                                    <tr><td>UTM source and campaign</td><td class="yes">Yes</td><td class="no">No</td></tr>
                                    <tr><td>New or returning shoppers</td><td class="yes">Yes</td><td class="no">No</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="docs-step" id="step-5">
                    <span class="docs-num">5</span>
                    <div>
                        <h3>Primary metric</h3>
                        <p>The one number that decides the winner. Pick it before you launch and don't change it afterwards.</p>
                        <div class="docs-table">
                            <table>
                                <thead><tr><th>Metric</th><th>What it measures</th><th>Best for</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>Conversion rate</strong></td><td>Share of test visitors who placed an order after first seeing the widget.</td><td>Most tests: trust, countdowns, sticky add to cart, checkout blocks.</td></tr>
                                    <tr><td><strong>Revenue per visitor</strong></td><td>Order revenue divided by visitors, so larger orders count.</td><td>Upsells and offers that change order size as much as conversion.</td></tr>
                                    <tr><td><strong>Revenue</strong></td><td>Total revenue, compared per visitor so an uneven split stays fair.</td><td>When revenue is the goal; it's tested the same way as revenue per visitor.</td></tr>
                                    <tr><td><strong>Click-through rate</strong></td><td>Share of test visitors who clicked the widget (a button, link or accepting its offer).</td><td>Thank You and Order Status blocks, where the order is already placed.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="docs-step" id="step-6">
                    <span class="docs-num">6</span>
                    <div>
                        <h3>Secondary metrics</h3>
                        <p>Extra numbers shown alongside the result to explain it. They don't decide the winner. Choose from add-to-cart rate, checkout-started rate, purchase rate, average order value, units per order, upsell acceptance, bundle completion and click-through rate.</p>
                    </div>
                </div>

                <div class="docs-step" id="step-7">
                    <span class="docs-num">7</span>
                    <div>
                        <h3>Guardrails</h3>
                        <p>Guardrails protect against a "winner" that quietly hurts something else. A variant that breaks a guardrail is never declared the winner, even if its primary metric improves.</p>
                        <ul class="docs-list">
                            <li><strong>Cart abandonment</strong>: share of visitors who added to cart but didn't buy.</li>
                            <li><strong>Negative interactions</strong>: closes and declined offers per visitor.</li>
                        </ul>
                        <p>The threshold is the largest increase you accept, in percentage points compared with the control. With a 5-point threshold, a control at 60% cart abandonment and a variant at 66% breaks the guardrail (+6 points).</p>
                    </div>
                </div>

                <div class="docs-step" id="step-8">
                    <span class="docs-num">8</span>
                    <div>
                        <h3>Duration and sample</h3>
                        <p>The minimums every variant must reach before a winner can be named: at least <strong>7 days</strong>, <strong>1,000 visitors</strong> and <strong>100 conversions</strong> (or clicks, for click-through tests). You can raise them, not lower them. Seven days covers a full weekly cycle, since weekday and weekend shoppers often behave differently.</p>
                        <p>An <strong>end date</strong> is optional. If you set one, it must leave at least the minimum duration, and the test completes on its own on that date.</p>
                    </div>
                </div>

                <div class="docs-step" id="step-9">
                    <span class="docs-num">9</span>
                    <div>
                        <h3>Preview and launch</h3>
                        <p>See every variant side by side as shoppers will see it (previews use your last saved setup). If anything blocks launching, it's listed here: allocation not adding up to 100%, an unpublished widget, another running test on the same widget, a variant identical to the control, or a plan without A/B testing. Click <strong>Launch test</strong>. The split starts on your store within a minute.</p>
                    </div>
                </div>
            </section>

            {{-- 5 --}}
            <section id="checkout">
                <h2>Checkout and Thank You blocks</h2>
                <p>Checkout, Thank You and Order Status blocks are tested inside Shopify's checkout by the OrderOrbit Space checkout extension, wherever the OrderOrbit Space block for that type is placed in the checkout editor.</p>
                <ul class="docs-list">
                    <li><strong>Audience</strong>: checkout only knows the cart value and the buyer's country, so those are the audience options.</li>
                    <li><strong>Metrics</strong>: checkout blocks usually use conversion rate (did the buyer complete the order). Thank You and Order Status blocks default to click-through rate, because the order is already placed; their "purchase rate" counts later orders.</li>
                    <li><strong>Blocks inside checkout</strong> need Shopify Plus (development stores can preview them). Thank You and Order Status blocks work on every Shopify plan.</li>
                    <li>While the extension loads the buyer's assignment, the block waits a moment instead of flashing one version and then another.</li>
                </ul>
            </section>

            {{-- 6 --}}
            <section id="assignment">
                <h2>How visitors are split</h2>
                <ul class="docs-list">
                    <li><strong>Stable assignment.</strong> Each visitor gets a random id stored in their browser (in checkout, in the extension's storage). The variant comes from a hash of the test and that id, so the same visitor always lands in the same variant, on every page and every visit.</li>
                    <li><strong>Audience first.</strong> Visitors outside the audience see the widget as published and aren't counted in the results.</li>
                    <li><strong>Exposure.</strong> A visitor joins the test the first time they see the widget; that's recorded once. They belong to that variant from then on.</li>
                    <li><strong>What counts.</strong> Orders, adds to cart, checkouts and clicks after a visitor's first exposure, until the test ends. Orders placed before the visitor saw the test never count.</li>
                    <li><strong>Holdout.</strong> Visitors in a holdout group are counted as exposed, but the widget isn't shown to them.</li>
                    <li><strong>Consent.</strong> Results only include shoppers who allow analytics in your cookie banner. The split itself still applies to everyone.</li>
                </ul>
            </section>

            {{-- 7 --}}
            <section id="results">
                <h2>Reading the results</h2>
                <p>Open a running or finished test from <strong>A/B tests</strong>. The banner at the top tells you where the test stands:</p>
                <div class="docs-states">
                    <div class="s-collecting"><strong>Collecting data</strong><span>The test hasn't reached its minimum days, visitors or conversions in every variant yet. It shows what's missing and a progress bar. No winner is shown, however good a variant looks.</span></div>
                    <div class="s-winner"><strong>Winner declared</strong><span>A variant is significantly better than the control on the primary metric, all minimums are met and no guardrail is broken. You can apply it.</span></div>
                    <div class="s-control"><strong>The control wins</strong><span>Every variant did significantly worse than the control. Keep the widget as it is.</span></div>
                    <div class="s-none"><strong>No clear winner</strong><span>The minimums are met but the difference isn't statistically significant. The change probably doesn't matter much; keep whichever you prefer, or test a bolder idea.</span></div>
                    <div class="s-guard"><strong>Guardrail breached</strong><span>A variant improved the primary metric but broke a guardrail, so it isn't declared the winner.</span></div>
                </div>

                <h3>The variants table</h3>
                <div class="docs-table">
                    <table>
                        <thead><tr><th>Column</th><th>Meaning</th></tr></thead>
                        <tbody>
                            <tr><td>Traffic</td><td>The share of the test's traffic set for the variant.</td></tr>
                            <tr><td>Visitors</td><td>Visitors exposed to the variant (the sample size).</td></tr>
                            <tr><td>Conversions</td><td>Visitors who placed at least one order after exposure.</td></tr>
                            <tr><td>Conversion rate</td><td>Conversions ÷ visitors.</td></tr>
                            <tr><td>Revenue</td><td>Total value of those visitors' orders after exposure.</td></tr>
                            <tr><td>Revenue / visitor</td><td>Revenue ÷ visitors.</td></tr>
                            <tr><td>AOV</td><td>Revenue ÷ orders.</td></tr>
                            <tr><td>Lift vs control</td><td>How much better or worse the variant is than the control on the primary metric, in percent, with its confidence interval in brackets. An interval that includes 0 means the difference could be chance.</td></tr>
                            <tr><td>p-value</td><td>The probability of seeing a difference at least this big if the variants were really the same. Smaller is stronger evidence; it must be below the significance level (0.05, or 0.025 per comparison in an A/B/C test).</td></tr>
                        </tbody>
                    </table>
                </div>
                <h3>The rest of the page</h3>
                <ul class="docs-list">
                    <li><strong>Conversion rate over time</strong>: cumulative conversion per variant. Early lines jump around; they settle as visitors accumulate.</li>
                    <li><strong>Secondary metrics</strong> and <strong>Guardrails</strong>: each variant's value, with OK or Breached for guardrails.</li>
                    <li><strong>By device</strong>: visitors and conversion per variant on mobile and desktop.</li>
                    <li><strong>Statistical method</strong> and <strong>History</strong>: how the result is calculated, and every launch, pause, resume and stop.</li>
                    <li><strong>Export CSV</strong>: the variants table as a spreadsheet.</li>
                </ul>
                <p>Test results also appear in <strong>Analytics → Revenue &amp; attribution</strong> (revenue per variant) and in <strong>Event Explorer</strong> (break any event down by A/B test or variant).</p>
            </section>

            {{-- 8 --}}
            <section id="method">
                <h2>The statistical method</h2>
                <p>OrderOrbit Space uses a fixed-horizon, frequentist test, the standard approach for e-commerce experiments:</p>
                <div class="docs-table">
                    <table>
                        <thead><tr><th>Metric</th><th>Test</th></tr></thead>
                        <tbody>
                            <tr><td>Conversion rate, click-through rate</td><td>Two-proportion z-test (pooled standard error for the p-value, unpooled for the interval)</td></tr>
                            <tr><td>Revenue per visitor, revenue, AOV</td><td>Welch's t-test (doesn't assume equal variances; Welch–Satterthwaite degrees of freedom)</td></tr>
                        </tbody>
                    </table>
                </div>
                <ul class="docs-list">
                    <li><strong>Confidence</strong>: 95%. In an A/B/C test, each variant is compared with the control, so the significance level is split between the two comparisons (Bonferroni correction: 0.05 ÷ 2 = 0.025 each). That keeps the chance of a false winner at 5% overall.</li>
                    <li><strong>Winner rule</strong>: all of these must be true:
                        <ol>
                            <li>The test has run at least the minimum days (7 or more).</li>
                            <li>Every variant has at least the minimum visitors (1,000 or more) and conversions or clicks (100 or more).</li>
                            <li>The variant is better than the control on the primary metric, with p below the significance level.</li>
                            <li>The variant hasn't broken any guardrail.</li>
                        </ol>
                    </li>
                    <li>If several variants qualify, the one with the largest lift wins.</li>
                </ul>
                <pre class="code"><code>Conversion:  z = (p_B − p_A) / √( p(1 − p) · (1/n_A + 1/n_B) ),  p = pooled conversion rate
Revenue:     t = (mean_B − mean_A) / √( s_A²/n_A + s_B²/n_B )
Lift:        (B − A) / A,  shown with its confidence interval</code></pre>
                <p class="docs-note">Attribution and tests measure what happened to the visitors in each group. With randomised groups, a significant difference is good evidence the change caused it, but no test is certain: at 95% confidence, about 1 in 20 tests of a change that does nothing will still look significant.</p>
            </section>

            {{-- 9 --}}
            <section id="manage">
                <h2>Pause, stop and apply a winner</h2>
                <div class="docs-table">
                    <table>
                        <thead><tr><th>Action</th><th>What happens</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Pause</strong></td><td>Everyone sees the control until you resume. Visitors keep their variant. While paused you can edit the setup.</td></tr>
                            <tr><td><strong>Resume</strong></td><td>The split starts again with the same assignments.</td></tr>
                            <tr><td><strong>Stop test</strong></td><td>Ends the test early. Everyone sees the widget as published, and the result at that moment is saved.</td></tr>
                            <tr><td><strong>Apply winner</strong></td><td>Publishes the winning variant's template, text and design to the widget for everyone, and completes the test.</td></tr>
                            <tr><td><strong>End date</strong></td><td>A test with an end date completes on its own that day.</td></tr>
                            <tr><td><strong>Duplicate as new test</strong></td><td>Copies the setup into a new draft, for a follow-up test.</td></tr>
                            <tr><td><strong>Delete</strong></td><td>Removes a draft or a finished test. Running tests must be stopped first.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>Only one test can run on a widget at a time. Editing the widget itself while a test runs changes what every variant inherits, so avoid it until the test ends.</p>
            </section>

            {{-- 10 --}}
            <section id="practices">
                <h2>Best practices</h2>
                <ul class="docs-list">
                    <li><strong>Test one idea at a time.</strong> If B changes the layout and the headline and the colours, a win won't tell you which change mattered.</li>
                    <li><strong>Make the change big enough to matter.</strong> Tiny tweaks need huge samples to detect. Bold, meaningful changes reach a result sooner.</li>
                    <li><strong>Run full weeks.</strong> Let tests run in whole weeks (7, 14, 21 days) so every weekday is represented equally.</li>
                    <li><strong>Don't stop early because it looks good.</strong> Early results swing a lot. The app won't name a winner before the minimums, and neither should you.</li>
                    <li><strong>Avoid big events mid-test.</strong> A flash sale or a viral post changes who visits. If one happens, consider extending the test.</li>
                    <li><strong>Keep a record.</strong> Write the hypothesis, and after the test, what you learned. "No clear winner" is a useful result too.</li>
                </ul>
                <h3>How many visitors do I need?</h3>
                <p>Visitors needed <strong>per variant</strong> to detect a relative improvement in conversion rate, at 95% confidence and 80% power:</p>
                <div class="docs-table">
                    <table>
                        <thead><tr><th>Current conversion rate</th><th>Detect +10%</th><th>Detect +20%</th><th>Detect +30%</th></tr></thead>
                        <tbody>
                            <tr><td>1%</td><td>163,100</td><td>42,700</td><td>19,900</td></tr>
                            <tr><td>2%</td><td>80,700</td><td>21,200</td><td>9,800</td></tr>
                            <tr><td>3%</td><td>53,300</td><td>14,000</td><td>6,500</td></tr>
                            <tr><td>5%</td><td>31,300</td><td>8,200</td><td>3,800</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>Example: a store converting at 2% that wants to detect a 20% lift (2% → 2.4%) needs about 21,200 visitors per variant, so about 42,400 for an A/B test. With 3,000 visitors a day seeing the widget, that's about two weeks. Find your conversion rate in Analytics. Click-through tests use the same table with click-through rate.</p>
            </section>

            {{-- 11 --}}
            <section id="limits">
                <h2>Limits</h2>
                <ul class="docs-list">
                    <li>Bundles, Progressive gifts, the post-purchase offer and customer account blocks can't be tested yet.</li>
                    <li>Variants can't change products, prices, discounts or thresholds.</li>
                    <li>Refund rate isn't available as a guardrail: refunds can't be reliably matched to test visitors.</li>
                    <li>A visitor on a new device or browser gets a new id, so they may see a different variant there.</li>
                    <li>Results only include shoppers who allow analytics.</li>
                    <li>Customer segments as a test audience arrive with Audiences &amp; Personalization.</li>
                </ul>
            </section>

            {{-- 12 --}}
            <section id="faq">
                <h2>Troubleshooting and FAQ</h2>
                @include('site.partials.faq', ['openFirst' => false, 'faqs' => [
                    ['The test shows 0 visitors.', 'Check that the widget shows on your store (its block is placed, and its page and targeting match), that Analytics is connected, and that you browse with analytics cookies allowed. Visitors appear within a few minutes of seeing the widget.'],
                    ['I always see the same variant. How do I check the others?', 'That\'s stable assignment working. Use the previews on the setup page, or open your store in a private window: each new private window is a new visitor.'],
                    ['Why is there no winner even though B looks better?', 'Either the minimums aren\'t met yet (the banner lists what\'s missing) or the difference isn\'t statistically significant. Small differences often disappear with more data.'],
                    ['Can I change the test while it runs?', 'Pause it first; then you can edit the setup and resume. Changing variants mid-test mixes two experiments, so prefer stopping and duplicating for a new idea.'],
                    ['What happens to visitors when the test ends?', 'Everyone sees the widget as published, or the winner if you applied it.'],
                    ['Does testing slow down my store?', 'No. The test script is under 1 KB and only loads on pages with a widget under test.'],
                    ['Is the visitor id personal data?', 'It\'s a random id with no name, email or address, stored in the shopper\'s browser to keep their variant stable.'],
                    ['Which plan do I need?', 'Growth or Scale to launch tests. You can set tests up on any plan.'],
                ]])
            </section>

            <section class="docs-cta">
                <h2>Ready to run your first test?</h2>
                <p>Open OrderOrbit Space in your Shopify admin and go to <strong>A/B tests</strong>.</p>
                <div class="ctas"><a class="btn primary" href="{{ route('site.feature', 'ab-testing') }}">About A/B testing</a> <a class="btn" href="{{ route('site.help') }}#ab-testing">Help center</a> <a class="btn" href="{{ route('site.contact') }}">Contact us</a></div>
            </section>
        </article>
    </div>
</div>
@endsection
