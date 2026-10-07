<?php

namespace App\Support;

/**
 * The first version of every legal page. Seeded once; after that the pages live in the database
 * and are edited in the Internal Admin (Legal & policies). Bodies are Markdown with {{placeholders}}
 * filled from the business details (see Legal::PLACEHOLDERS).
 */
class LegalDefaults
{
    /** slug => [title, summary, ask merchants to review when updated, in footer, body] */
    public static function pages(): array
    {
        return [
            'terms' => ['Terms of Service', 'The agreement between your store and {{app_name}}.', true, true, self::TERMS],
            'privacy' => ['Privacy Policy', 'What data {{app_name}} collects, why, how long we keep it and your rights.', true, true, self::PRIVACY],
            'dpa' => ['Data Processing Addendum', 'How we process your shoppers\' personal data on your behalf.', true, true, self::DPA],
            'billing' => ['Billing, Cancellation & Refund Policy', 'How plans are charged through Shopify, cancellations and refunds.', true, true, self::BILLING],
            'acceptable-use' => ['Acceptable Use Policy', 'What {{app_name}} may not be used for.', false, true, self::AUP],
            'cookies' => ['Cookie & Storefront Storage Policy', 'What we store in browsers on our website and on your storefront.', false, true, self::COOKIES],
            'subprocessors' => ['Subprocessors', 'Third parties that help us run {{app_name}}.', false, true, self::SUBPROCESSORS],
            'support' => ['Support Policy', 'How to reach us, response times and what support covers.', false, true, self::SUPPORT],
        ];
    }

    private const TERMS = <<<'MD'
These Terms of Service ("Terms") are an agreement between you, the merchant who installs {{app_name}} on a Shopify store ("you", "Merchant"), and {{operator}}, the individual developer who builds and operates {{app_name}} ("we", "us", "our"). {{app_name}} (the "App" or "Service") is the name of a product, not a separate company; when these Terms say "we", they mean {{operator}}. By installing, accessing or using the App you agree to these Terms on behalf of the business that owns the store. If you do not agree, do not install or use the App.

These Terms include the documents they link to: the [Privacy Policy]({{url:privacy}}), the [Data Processing Addendum]({{url:dpa}}), the [Billing, Cancellation & Refund Policy]({{url:billing}}), the [Acceptable Use Policy]({{url:acceptable-use}}) and the [Support Policy]({{url:support}}).

## 1. Shopify and these Terms

The App is a third-party app for the Shopify platform. Shopify Inc. and its affiliates ("Shopify") are not a party to these Terms and are not responsible for the App. Your use of Shopify is governed by Shopify's own terms. If these Terms conflict with Shopify's terms for app users, Shopify's terms apply to the extent of the conflict.

## 2. Who may use the App

You must be at least 18 years old and authorised to bind the business that owns the store. You are responsible for everyone you allow to use the App through your Shopify admin, including staff and collaborators, and for keeping your Shopify account secure.

## 3. The Service

The App provides conversion tools for Shopify stores, including bundles, progressive gifts, cart upsells, countdown timers, sticky add to cart, pre-orders, sales pop, trust badges, checkout and customer-account blocks, analytics, A/B testing, audiences and personalization, and automation workflows. The features available to you depend on your plan.

We may add, change or remove features, templates or limits. We will not remove a core feature of a paid plan during a billing cycle you have already paid for, unless Shopify, the law or a security issue requires it. Features marked beta, preview or early access are provided for evaluation, may change or end at any time, and are excluded from any commitment in these Terms.

## 4. Your responsibilities

You decide what the App shows and does in your store. In particular, you are responsible for:

- **Your offers and prices.** Configuring, reviewing and testing every bundle, discount, gift, upsell, pre-order and price before you publish it, and checking it in your storefront and checkout.
- **Truthful marketing.** Making sure countdowns, stock messages, sales pop, badges, reviews and other claims are accurate and lawful where you sell, including consumer-protection, advertising and pricing rules.
- **Your customers.** Having a privacy notice that covers the use of the App, collecting any consent your law requires (for example for cookies, analytics or marketing emails), and honouring your customers' privacy requests.
- **Your content.** Owning or being licensed to use the text, images and other content you add to the App.
- **Your store setup.** Your theme, other apps and store settings. We support the App's own blocks and embeds; changes made by other apps, theme updates or custom code can affect how the App appears.
- **Taxes and fulfilment.** Taxes, shipping, fulfilment, returns and customer service for orders placed in your store, including orders that use the App's discounts, gifts or pre-orders.

## 5. Discounts, checkout and orders

The App creates discounts and cart changes using Shopify's tools (such as Shopify Functions and discount APIs). Shopify calculates the final price at checkout, and Shopify's rules on combining discounts apply. If an offer is set up incorrectly, or combines with other discounts in a way you did not intend, orders may be placed at a lower price than you expected.

You agree that you are responsible for orders placed while an offer you configured was live. We are not liable for revenue lost, or costs incurred, because of how an offer was configured, combined or displayed. If you believe the App applied a price incorrectly, pause the offer and tell us promptly so we can investigate.

## 6. Analytics, tests and results

Analytics, attribution, A/B test results, recommendations and revenue figures are estimates based on the data available to the App and on our models. They can differ from Shopify's reports or other tools, for example because of shopper consent, ad blockers, time zones, refunds or tracking limits. They are provided to help your decisions and are not guarantees.

**We do not guarantee any increase in conversion, average order value, revenue or any other result.**

## 7. Plans and billing

Paid plans are billed by Shopify, on your Shopify invoice, in the currency and at the price shown in the App when you subscribe. Each plan's features and usage limits are shown in the App and on our [Pricing]({{website}}/pricing) page. The [Billing, Cancellation & Refund Policy]({{url:billing}}) explains trials, plan changes, usage limits, cancellations and refunds.

## 8. Your data and privacy

You own the data in your store. You give us permission to access and process it only to provide, secure, support and improve the Service, as described in the [Privacy Policy]({{url:privacy}}). For personal data of your customers, you are the controller and we are your processor under the [Data Processing Addendum]({{url:dpa}}).

We may use aggregated, de-identified data that does not identify you, your store or any person, for example to measure performance across the platform and to improve the App.

## 9. Intellectual property

We own the App, including its software, design, templates, documentation and trademarks. Subject to these Terms and payment of any fees, we grant you a limited, non-exclusive, non-transferable, revocable licence to use the App for your own Shopify stores while it is installed.

You keep ownership of your content. You grant us a licence to host, copy, display and process it only as needed to provide the Service to you.

If you send us suggestions or feedback, we may use them without any obligation to you.

You must not copy, modify, resell, sublicense, reverse engineer or create derivative works of the App, or use it to build a competing product, except where the law expressly allows it.

## 10. Third-party services

The App depends on Shopify and on third-party services (see [Subprocessors]({{url:subprocessors}})). We are not responsible for third-party services, their outages, or changes they make, including changes to Shopify's APIs, checkout, themes or policies. If such a change requires it, we may change or remove an affected feature.

## 11. Availability and support

We work to keep the App available and reliable, but we do not guarantee that it will be uninterrupted, error-free or available at any particular time. We may carry out maintenance, which we try to schedule at quiet times. Support is provided as described in the [Support Policy]({{url:support}}).

## 12. Suspension and termination

You may stop using the App at any time by uninstalling it from your Shopify admin.

We may suspend or end your access, with notice where reasonably possible, if:

- you breach these Terms or the Acceptable Use Policy;
- your use creates a security, legal or operational risk;
- a payment is not made through Shopify; or
- Shopify or the law requires it.

We may also discontinue the App, giving at least 30 days' notice in the App or by email.

When the App is uninstalled or your access ends, its storefront features stop working and your data is deleted as described in the Privacy Policy. Sections 4, 5, 6, 8, 9 and 13 to 18 continue to apply after termination.

## 13. Disclaimer

To the fullest extent permitted by law, the App is provided **"as is" and "as available"**, without warranties of any kind, whether express, implied or statutory. This includes warranties of merchantability, fitness for a particular purpose, non-infringement and accuracy. Some laws do not allow these exclusions, so some of them may not apply to you.

## 14. Limitation of liability

To the fullest extent permitted by law:

- **(a) No liability for damages.** We are not liable to you or to anyone else for any loss or damage of any kind arising from or relating to the App, our services or these Terms. This includes direct, indirect, incidental, special, consequential, exemplary and punitive damages, and any loss of profits, revenue, sales, orders, goodwill, data or business opportunity, however caused and under any legal theory (contract, tort including negligence, or otherwise), even if we were told such loss was possible.
- **(b) No compensation.** We do not pay compensation, damages, credits or refunds for any claim. Any refund of App charges is at our sole discretion.
- **(c) Your remedy.** If you are not satisfied with the App, your only remedy is to stop using it and uninstall it.

Nothing in these Terms excludes or limits liability that cannot be excluded or limited by law, such as liability for fraud.

## 15. Indemnity

You will defend and indemnify us against third-party claims, losses and costs (including reasonable legal fees) that arise from any of the following:

- your store, products, offers, content or marketing;
- your breach of these Terms or of the law; or
- your use of the App in breach of any person's rights.

## 16. Changes to these Terms

We may update these Terms. If we make a material change, we will tell you in the App or by email at least {{notice_days}} days before it takes effect, unless the change is required sooner by law or by Shopify.

The date at the top of this page shows when the current version took effect. Continuing to use the App after a change takes effect means you accept it. If you do not accept a change, uninstall the App before it takes effect.

## 17. Governing law and disputes

These Terms are governed by {{governing_law}}, without regard to conflict-of-law rules. Before starting any formal proceedings, you agree to contact us and try in good faith to resolve the dispute informally for at least 30 days.

Any dispute that is not resolved will be subject to the exclusive jurisdiction of {{courts}}, except where the law of your country gives you a right to bring proceedings elsewhere.

## 18. General

- **Entire agreement.** These Terms are the entire agreement between you and us about the App.
- **Assignment.** You may not transfer these Terms without our consent. We may transfer them as part of a sale or reorganisation of the App.
- **Severability and waiver.** If any part of these Terms is unenforceable, the rest remains in force. Failing to enforce a right is not a waiver of it.
- **Force majeure.** We are not liable for delays or failures caused by events beyond our reasonable control, including outages of Shopify, hosting or internet providers.
- **Relationship.** You and we are independent; nothing creates a partnership, employment or agency. {{app_name}} is operated by an individual, so it may be helped by contractors, who are bound by confidentiality.
- **Language.** If these Terms are translated, the English version prevails.

## 19. Contact and notices

{{app_name}} is developed and operated by {{operator}}, an individual developer. {{contact_line}} Legal notices to us must be sent to that contact. Notices to you may be sent in the App or to your store's contact email.
MD;

    private const PRIVACY = <<<'MD'
This Privacy Policy explains how {{operator}} ("we", "us"), the individual developer who builds and operates {{app_name}} (the "App"), collects, uses, shares and protects personal data. It covers:

- **merchants** who install the App and their staff;
- **shoppers** who visit storefronts where the App is enabled; and
- **visitors** to our website, {{website}}.

## 1. Our role

- **Merchant and staff data, and website visitor data:** we are the controller.
- **Shopper data processed in a merchant's store:** the merchant is the controller and we are a processor acting on the merchant's instructions, under our [Data Processing Addendum]({{url:dpa}}). Shoppers should read the privacy policy of the store they shop at and contact that store to exercise their rights.

## 2. Data we collect

**From Shopify when you install and use the App**

- Store details: shop domain, store name, contact email, currency, time zone, Shopify plan and theme.
- The access tokens Shopify grants the App. These are stored encrypted.
- Staff who open the App: Shopify user ID, first name, email and role. We use these for permissions and the activity log.
- Order information needed to show sales pop and to run the workflows you set up:
  - order ID and date;
  - for sales pop, the product purchased and the shopper's country;
  - for workflows, the order details a workflow uses (for example its total and tags).

  We do not store shoppers' names, addresses or payment details from orders.
- Products, collections and themes, read as needed to build and place your offers.

**What you create in the App**

Offers, templates, settings, experiments, segments, personalization rules, automation workflows, support tickets and attachments, and an activity log of changes.

**On storefronts where the App is enabled**

- Shopping and interaction events, collected through the App's Shopify Web Pixel and storefront script, such as:
  - page and product views;
  - offer views and clicks;
  - add to cart, checkout started and checkout completed.
- Each event can include:
  - a random visitor and session identifier;
  - the Shopify customer ID if the shopper is logged in;
  - page type, product and variant;
  - device type and country;
  - traffic source and campaign;
  - experiment variant and order value.
- We do not collect shoppers' names, email addresses, postal addresses, payment details or precise location in analytics.
- Analytics events are collected only when the shopper's consent allows it under Shopify's Customer Privacy settings for the store.

**For automation**

If a merchant uses workflows that act on customers, the App processes the customer ID and the tags or emails the workflow prepares, which can include the customer's email address and first name.

**On our website**

- Information you send through the contact form: name, email, store and message.
- The IP address used to prevent abuse.
- The cookies described in the [Cookie & Storefront Storage Policy]({{url:cookies}}).

## 3. How we use data and our legal bases

- **To provide the App.** We show offers, apply discounts, run tests and workflows, show analytics and enforce plan limits. Legal basis: performance of our contract with the merchant.
- **To bill through Shopify and measure plan usage.** Legal basis: contract, and our legitimate interests.
- **To provide support.** This includes investigating issues you report. Legal basis: contract and legitimate interests.
- **To keep the App secure.** We detect abuse, verify Shopify requests and keep audit logs. Legal basis: legitimate interests and legal obligations.
- **To improve the App.** We use aggregated, de-identified statistics. Legal basis: legitimate interests.
- **To meet legal obligations.** This includes tax, accounting and lawful requests.

We do not sell personal data. We do not share personal data for cross-context behavioural advertising. We do not use merchant or shopper data to train third-party AI models.

## 4. Sharing

We share personal data only with:

- the [Subprocessors]({{url:subprocessors}}) that host and operate the App, under contracts that protect the data;
- Shopify, as needed to provide the App;
- authorities, where the law requires it or to protect rights and safety; and
- a successor, if the App is sold or reorganised. The successor must honour this policy.

## 5. International transfers

Our providers may process data in countries other than yours, including the United States. Where the law requires it, we rely on appropriate safeguards, such as the European Commission's Standard Contractual Clauses.

## 6. Retention

| Data | How long we keep it |
| --- | --- |
| Storefront analytics events | The period the merchant chooses in Settings → Privacy (3, 6 or 13 months; 13 by default), then deleted automatically |
| Store configuration, offers, workflows | While the App is installed |
| Sales pop purchases (product and country) | 30 days |
| Support tickets | While the App is installed, or longer if needed to resolve a dispute |
| Billing and audit records | As long as needed for legal, tax and security purposes |
| Contact-form messages | Up to 24 months |

**When a merchant uninstalls:** Shopify sends us a store-deletion request (shop/redact), normally 48 hours after uninstall, and we then delete the store's data. Copies in encrypted backups are removed as the backups expire.

**Customer requests:** when Shopify sends a customer-deletion request, we delete or anonymise that customer's data.

## 7. Security

We use the following measures:

- encryption in transit (HTTPS) and encrypted storage of access tokens;
- separation of each store's data;
- role-based access for merchant staff and our own team;
- verification of every request from Shopify; and
- audit logs.

No system is perfectly secure, but we work to protect data and will notify affected merchants of a personal data breach without undue delay.

## 8. Your rights

Depending on where you live, you may have rights to:

- access, correct, delete or export your personal data;
- restrict or object to processing; and
- withdraw consent.

These rights come from laws including the GDPR and UK GDPR, the California Consumer Privacy Act (as amended by the CPRA) and other US state laws, Canada's PIPEDA and India's Digital Personal Data Protection Act, 2023. We will not discriminate against you for exercising them.

- **Merchants:** contact us at {{privacy_contact}}. In the App, Settings → Privacy lets you export your data and delete analytics.
- **Shoppers:** contact the store you shopped at. We support every customer data request and customer-deletion request Shopify sends us on the store's behalf.

You may complain to your local data protection authority. We would appreciate the chance to resolve your concern first.

## 9. Children

The App and our website are for businesses and are not directed to children. We do not knowingly collect children's personal data.

## 10. Changes

We will post changes to this policy on this page and update the effective date. If a change is material, we will also notify merchants in the App.

## 11. Contact and grievances

{{app_name}} is developed and operated by {{operator}}, an individual developer, who is responsible for your personal data under this policy. {{contact_line}} For privacy questions, requests or grievances, contact {{privacy_contact}}. We aim to respond within 30 days.
MD;

    private const DPA = <<<'MD'
This Data Processing Addendum ("DPA") forms part of the [Terms of Service]({{url:terms}}) between the merchant ("Controller") and {{operator}}, the individual developer who operates {{app_name}} ("Processor"). It applies when the Processor processes personal data of the Controller's customers and store visitors ("Personal Data") to provide the App.

## 1. Roles and instructions

The Controller determines the purposes and means of processing. The Processor processes Personal Data only:

- on the Controller's documented instructions, which are these Terms, this DPA and the Controller's configuration of the App; or
- where the law requires it.

If the law requires it, the Processor will inform the Controller before processing, unless the law prohibits doing so. The Processor will tell the Controller if it believes an instruction breaks data protection law.

## 2. Details of processing

| Item | Details |
| --- | --- |
| Subject matter | Providing the App's conversion, analytics, testing, personalization and automation features |
| Duration | While the App is installed, plus the deletion period in the Privacy Policy |
| Nature and purpose | Collecting storefront events, measuring offers and tests, segmenting visitors, running workflows the Controller sets up |
| Data subjects | The Controller's store visitors and customers |
| Categories of data | Random visitor and session IDs, Shopify customer ID, page, product and cart events, order value, device type, country, traffic source, experiment variant; for automation, customer email address, first name and tags |
| Special categories | None. The Controller must not configure the App to process special-category data |

## 3. Confidentiality and personnel

The Processor is an individual developer. The Processor ensures that the Processor and anyone else authorised to process Personal Data (for example a contractor) are bound by confidentiality and access it only as needed to provide, secure or support the App.

## 4. Security

The Processor implements appropriate technical and organisational measures, including:

- encryption in transit;
- encrypted storage of access credentials;
- logical separation of each store's data;
- role-based access control;
- verification of requests from Shopify;
- audit logging;
- retention controls; and
- tools to export and delete data.

## 5. Subprocessors

The Controller authorises the Processor to use the subprocessors listed on the [Subprocessors]({{url:subprocessors}}) page. The Processor will:

- bind each subprocessor to data-protection obligations no less protective than this DPA;
- remain responsible for each subprocessor's performance; and
- give notice of a new subprocessor by updating that page, and in the App where the change is material.

If the Controller objects on reasonable data-protection grounds, its remedy is to stop using the affected feature or uninstall the App.

## 6. Assistance

Taking into account the nature of the processing, the Processor will reasonably assist the Controller with data-subject requests, data protection impact assessments and consultations with authorities. Shopify's mandatory compliance webhooks (customer data request, customer redact and shop redact) are handled automatically.

## 7. Personal data breaches

The Processor will notify the Controller without undue delay, and where feasible within 72 hours, after becoming aware of a personal data breach affecting the Controller's Personal Data. The notice will include the information reasonably available to help the Controller meet its own obligations.

## 8. Deletion

When the App is uninstalled, the Processor deletes Personal Data after receiving Shopify's shop redact request, except where the law requires it to keep data. Data in backups is deleted as the backups expire.

## 9. Audits

On written request, no more than once a year, the Processor will provide the information reasonably necessary to demonstrate compliance with this DPA, for example answers to a security questionnaire. Any further audit must be agreed in advance, carried out at the Controller's cost and subject to confidentiality.

## 10. International transfers

Where Personal Data is transferred outside the EEA, the UK or Switzerland to a country without an adequacy decision, the Standard Contractual Clauses (and the UK Addendum, where applicable) are incorporated by reference, with the Controller as data exporter and the Processor as data importer.

## 11. Liability and precedence

Each party's liability under this DPA is subject to the limitations in the Terms of Service, to the extent permitted by law. If this DPA conflicts with the Terms on the processing of Personal Data, this DPA prevails.
MD;

    private const BILLING = <<<'MD'
This policy explains how {{app_name}} plans are charged, changed and cancelled. It forms part of the [Terms of Service]({{url:terms}}).

## 1. Billing through Shopify

All charges are made by Shopify and appear on your Shopify invoice. We never collect your card details. Prices are in US dollars unless the App shows otherwise. Shopify adds any applicable taxes. Payment disputes and failed payments are handled under Shopify's billing terms.

## 2. Plans and free trials

Plans, prices, included features and limits are shown in the App under Settings → Billing and on our [Pricing]({{website}}/pricing) page. Where a plan has a free trial, you are not charged until the trial ends. Uninstalling before the trial ends means you are not charged. Trials are available once per store.

## 3. Usage limits

Each plan includes a set of features and usage limits, such as the number of live experiences, bundles, gift campaigns, shipping bars and workflows, and automation runs per month.

- **Warning:** the App warns you as you get close to a limit, and shows your usage in Settings → Billing.
- **At a limit:** everything already live keeps working; you can't add more until you upgrade.
- **Your data:** limits never delete your data or settings.

## 4. Upgrades and downgrades

You can change plans at any time in Settings → Billing.

- **Upgrades** take effect immediately.
- **Downgrades** take effect as Shopify applies them. Shopify prorates or credits charges according to its own billing rules.
- **Limits after a downgrade:** if you now have more live offers than the new plan allows, the extra offers are paused, not deleted.

## 5. Cancellation

To cancel, uninstall the App from your Shopify admin, or switch to the Free plan. Charges stop from your next Shopify billing cycle. Uninstalling ends the subscription. Your data is then deleted as described in the [Privacy Policy]({{url:privacy}}).

## 6. Refunds

Fees are charged in advance, and except as set out below they are **non-refundable**, including for partial months, unused features, unused time or downgrades.

We will refund or credit you through Shopify if:

- you were charged in error, for example a duplicate charge; or
- the App was unavailable for a significant part of a billing cycle because of a fault on our side.

We will also refund where the law requires it.

To request a refund, contact {{contact}} within 30 days of the charge, with your store domain and the charge details. Any refund is issued through Shopify's app-credit or refund tools. Goodwill refunds outside this policy are at our discretion and do not create an obligation to give others.

## 7. Price changes

We may change plan prices. We will give you at least 30 days' notice in the App or by email before a new price applies to your existing subscription. Shopify may also ask you to approve the new charge. If you do not accept the new price, you can change plans or uninstall before it applies.

## 8. Complimentary access

We may give a store free or discounted access, extra features or higher limits, for example for a partnership or a pilot. Complimentary access is at our discretion. It can be time-limited and ends on the date we tell you, or with 30 days' notice if no date was set.
MD;

    private const AUP = <<<'MD'
This Acceptable Use Policy forms part of the [Terms of Service]({{url:terms}}). It applies to everyone who uses {{app_name}}. You must follow it and Shopify's Acceptable Use Policy.

## 1. Honest selling

You must not use the App to mislead shoppers. This includes:

- **False deadlines.** Countdown timers that restart, or that show a deadline that is not real.
- **False scarcity or demand.** Stock levels, "people viewing" or demand messages that are not true.
- **Fake activity.** Fake or edited sales notifications, or notifications for purchases that did not happen.
- **Fake trust signals.** Reviews, ratings, badges, guarantees, certifications or endorsements you are not entitled to display.
- **False discounts.** Reference prices or savings that do not reflect a genuine previous or comparable price.
- **Hidden costs.** Unclear subscription, shipping or pre-order terms, or failing to disclose material conditions of an offer.

## 2. Lawful use

You must not use the App:

- to sell or promote illegal, counterfeit or prohibited products, or products Shopify does not allow;
- to discriminate unlawfully between shoppers, for example in prices or offers based on protected characteristics;
- to process special-category personal data (such as health or religion) or children's data;
- to send marketing messages without the consent or legal basis your law requires; or
- in breach of consumer-protection, privacy, advertising, export or sanctions laws.

## 3. Protecting the Service

You must not:

- access the App other than through your Shopify admin, or share access with people outside your business;
- try to get around plan limits, billing or security controls;
- probe, scan or test the App for vulnerabilities without our written permission. Please report vulnerabilities to {{contact}};
- interfere with or overload the App, for example by automated scraping or load testing;
- upload malware, or content that infringes someone else's rights; or
- copy, reverse engineer or resell the App, or use it to build a competing product.

## 4. Enforcement

If we reasonably believe this policy has been breached, we may:

- remove or pause the content or offer concerned;
- limit features; or
- suspend or end your access under the Terms.

Where it is safe and lawful, we will tell you first and give you a chance to fix the issue.
MD;

    private const COOKIES = <<<'MD'
This policy explains what {{app_name}} stores in web browsers, both on our website and on storefronts where merchants enable the App.

## 1. Our website

| Name | Purpose | Type | Duration |
| --- | --- | --- | --- |
| Session cookie | Keeps forms (such as the contact form) working and secure | Strictly necessary | Browser session |
| XSRF-TOKEN | Protects forms against cross-site request forgery | Strictly necessary | Browser session |
| Preview access | Remembers that you unlocked a preview of the site | Strictly necessary | 30 days |

We do not use advertising or cross-site tracking cookies on our website. Our pages load fonts from Google Fonts, which receives your IP address to deliver them.

## 2. Merchants' storefronts

The App does not set its own cookies on storefronts. It uses the browser's local storage and session storage to make its features work and to measure them:

| Key | Purpose | Storage |
| --- | --- | --- |
| oo_vid | Random visitor ID, used to keep A/B test variants and offers consistent and to count unique visitors | Local storage, until cleared |
| oo_s | Random session ID for the current visit | Session storage |
| oo_ix | Which offers a visitor has seen or used, for frequency limits and personalization | Local storage, until cleared |
| oo_dismiss_* | Remembers that a visitor closed an offer, so it is not shown again | Local storage, until cleared |
| oo_xp_* | The A/B test variant assigned in this visit | Session storage |
| Other oo_* keys | Display frequency for popups and notifications | Session or local storage |

These values are random identifiers or flags. They do not contain names, email addresses or payment details.

Analytics events are sent to us only when the shopper's consent allows it, using Shopify's Customer Privacy settings and the Shopify Web Pixel. Merchants are responsible for their store's consent banner and for describing the App in their own privacy and cookie notices.

## 3. Your choices

You can clear or block browser storage in your browser settings. Some App features may then show again or not stay consistent. On storefronts, withdrawing analytics consent through the store's consent banner stops analytics events from being collected.
MD;

    private const SUBPROCESSORS = <<<'MD'
{{app_name}} uses the following third parties to provide the App. Each is bound by data-protection obligations appropriate to the data it processes.

| Subprocessor | Purpose | Data | Location |
| --- | --- | --- | --- |
| Shopify Inc. | The platform the App runs on; billing; source of store, product and order data | All App data that comes from Shopify | Canada, United States and other Shopify locations |
| Railway Corporation | Application hosting, database and background jobs | All data stored by the App | United States |
| Google LLC (Google Fonts) | Fonts on our public website only | Website visitors' IP addresses | Global |

When we add or replace a subprocessor that processes merchants' or shoppers' personal data, we will update this page and, where the change is material, notify merchants in the App. See the [Data Processing Addendum]({{url:dpa}}).
MD;

    private const SUPPORT = <<<'MD'
This policy describes the support we provide for {{app_name}}. It forms part of the [Terms of Service]({{url:terms}}).

## 1. How to reach us

- **In the App:** Support (recommended), with your store details attached automatically.
- **Our website:** the [contact page]({{website}}/contact), or {{contact}}.

Support is provided in English during {{support_hours}}.

## 2. Response times

We aim to send a first response within {{response_time}}. Plans that include priority support are answered first. These are targets, not guarantees, and resolution times depend on the issue.

If an offer is showing the wrong price or blocking checkout, **pause it in the App straight away** and tell us. Pausing usually takes effect within seconds.

## 3. What support covers

**Covered**

- Setting up and using the App's features.
- Placing the App's blocks and embeds in Shopify's Theme Editor.
- Investigating bugs in the App.
- Billing questions about the App.

**Not covered**

- Custom theme development.
- Issues caused by other apps, custom code or theme edits we did not make.
- Shopify platform issues.
- Marketing or conversion strategy beyond how the App's features work.

## 4. Access to your store

To investigate an issue, we may look at your App configuration and the data the App holds, only as needed and only for your request. If we need collaborator access to your Shopify admin, we will ask, and you can approve or decline it in Shopify. You can remove our access at any time.

## 5. Feature requests and changes

We welcome feature requests, but we cannot commit to building them or to a timeline. We may change how support is provided, and will update this page when we do.

## 6. Availability and maintenance

We monitor the App and work to fix incidents promptly. We do not offer a guaranteed uptime commitment or service credits unless agreed in writing. Where possible, planned maintenance happens at quiet times.
MD;
}
