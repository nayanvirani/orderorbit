<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Saved replies for "Suggest reply" (G35), with a starter set the team can edit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('keywords', 500)->nullable();
            $table->text('body');
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamps();
        });

        $sign = "\n\nGrowvia Support";
        $rows = [
            ['Bundle discount missing at checkout', 'bundle discount checkout price missing not applied variant mapped', "Hi {{first_name}},\n\nThanks for reaching out. When a bundle's saving doesn't show at checkout, it's usually a variant that was added after the bundle was saved. Open Growvia › CRO › Bundles, open the bundle, tick the missing variant under Products and press Publish. The discount applies straight away."],
            ['Widget not showing on the store', 'not showing widget missing visible store theme embed block appear', "Hi {{first_name}},\n\nThanks for the message. If a widget doesn't show, check two things: 1) In Shopify go to Online Store › Themes › Customize › App embeds and make sure Growvia is on, then Save. 2) For widgets placed as a block, add the Growvia block on that page in the Theme Editor and choose the widget type. On {{shop}} you have {{live_widgets}} live widgets right now."],
            ['Checkout blocks need Shopify Plus', 'checkout block plus shopify plan not available editor', "Hi {{first_name}},\n\nBlocks inside the checkout steps need Shopify Plus; that's a Shopify rule for all apps. Thank You and Order Status blocks work on every Shopify plan, so you can use cross-sells, reorder and surveys there today."],
            ['Change plan', 'plan upgrade downgrade change billing price subscription', "Hi {{first_name}},\n\nYou're on the {{plan}} plan. You can switch any time in Growvia › Settings › Plan and billing; Shopify shows the new price for you to approve, and your widgets and settings stay as they are."],
            ['Refund or charge question', 'refund charge invoice billed money back cancel billing', "Hi {{first_name}},\n\nGrowvia is billed through Shopify, so charges appear on your Shopify invoice. Could you tell me the invoice date and amount? I'll check it for you right away."],
            ['Uninstall or cancel', 'uninstall cancel remove delete close account stop', "Hi {{first_name}},\n\nSorry to see you go. You can uninstall from Shopify › Settings › Apps and sales channels; billing stops automatically and your theme is left exactly as it was. If something wasn't working, I'd love to fix it first; just reply with what you needed."],
            ['Pre-order products can not be bought', 'preorder pre-order out of stock sold out buy button inventory', "Hi {{first_name}},\n\nFor pre-orders, turn on \"Continue selling when out of stock\" for those products in Shopify (Products › the product › Inventory). Growvia then shows the ship date and marks each item \"Pre-order\" on the order."],
            ['Sales pop not showing', 'sales pop popup notification recent purchase not showing', "Hi {{first_name}},\n\nSales pop uses your real recent orders and appears through the Growvia app embed, so make sure the embed is on (Online Store › Themes › Customize › App embeds). Pops start once the first orders are recorded, and only for shoppers who accept analytics in your cookie banner."],
            ['Automation email not sent', 'automation workflow email not sent skipped no email address', "Hi {{first_name}},\n\nI checked your workflow. Email steps need the customer's email from Shopify; Growvia › Automation › Runs shows each step and why one was skipped. If it says \"no email address\", the order didn't include one."],
            ['Feature request: thanks', 'feature request idea suggestion would be great add support for', "Hi {{first_name}},\n\nThank you for the idea, it's a good one. I've added it to our list and will let you know when it's available."],
        ];
        foreach ($rows as [$title, $keywords, $body]) {
            DB::table('saved_replies')->insert(['title' => $title, 'keywords' => $keywords, 'body' => $body.$sign, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_replies');
    }
};
