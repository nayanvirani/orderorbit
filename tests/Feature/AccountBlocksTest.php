<?php

namespace Tests\Feature;

use App\Experiences\Registry;
use App\Experiences\Schema;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\PublishException;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class AccountBlocksTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        app(TemplateLibrary::class)->sync();
        Http::fake(fn (Request $request) => match (true) {
            str_contains($request['query'] ?? '', 'currentAppInstallation { id } shop {') => Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => 'gid://shopify/Shop/7', 'primaryDomain' => ['url' => 'https://vantora.example']]]]),
            str_contains($request['query'] ?? '', 'metafieldDefinitionCreate') => Http::response(['data' => ['metafieldDefinitionCreate' => ['createdDefinition' => ['id' => 'gid://shopify/MetafieldDefinition/1'], 'userErrors' => []]]]),
            str_contains($request['query'] ?? '', 'metafieldsSet') => Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]),
            default => Http::response(['data' => []]),
        });
    }

    public function test_seven_account_blocks_with_account_only_settings(): void
    {
        $types = array_keys(array_filter(Registry::types(), fn ($t) => $t['surface'] === 'account'));
        $this->assertSame(['account-orders', 'account-tracking', 'account-reorder', 'account-rewards', 'account-reviews', 'account-products', 'account-support'], $types);

        $fields = Schema::fields('account-rewards');
        $this->assertArrayHasKey('ck_background', $fields['design']);
        $this->assertSame(['priority'], array_keys($fields['behavior']));
        $this->assertSame(['countries'], array_keys($fields['targeting']), 'Customer accounts have no cart.');
        foreach ($types as $type) {
            $this->assertSame([], Schema::normalize($type, Schema::defaults($type))[1], "{$type} works out of the box.");
        }
        $this->assertSame('Open customer accounts editor', $this->installedStore()->editorLabel('account'));
    }

    public function test_publishing_needs_growth_and_new_customer_accounts(): void
    {
        $manager = app(ExperienceManager::class);

        $starter = $this->installedStore(['shop_domain' => 'starter.myshopify.com', 'plan' => 'starter', 'capabilities' => ['new_customer_accounts' => true]]);
        try {
            $manager->publish($manager->create($starter, 'account-support', 'support-card', null), null);
            $this->fail('Account blocks need Growth or Scale.');
        } catch (PublishException $e) {
            $this->assertSame(['plan', true], [$e->reason, str_contains($e->getMessage(), 'Customer account blocks')]);
        }

        $classic = $this->installedStore(['plan' => 'growth', 'capabilities' => ['new_customer_accounts' => false], 'capabilities_checked_at' => now()]);
        try {
            $manager->publish($manager->create($classic, 'account-support', 'support-card', null), null);
            $this->fail('Classic customer accounts can\'t show extension blocks.');
        } catch (PublishException $e) {
            $this->assertStringContainsString('new customer accounts', $e->getMessage());
        }
        $owner = $this->member($classic, 'owner');
        $this->page('/app/cro/features/customer-accounts', $owner)->assertOk()->assertJsonPath('props.notice', 'accounts')->assertJsonPath('props.types.0.singular', 'my orders block');
    }

    public function test_account_blocks_are_published_for_customer_accounts_only(): void
    {
        $manager = app(ExperienceManager::class);
        $store = $this->installedStore(['plan' => 'growth', 'capabilities' => ['new_customer_accounts' => true, 'checkout_blocks' => true]]);
        $rewards = $manager->create($store, 'account-rewards', 'tier-card', null);
        $manager->publish($rewards, null);
        $manager->publish($manager->create($store, 'account-reorder', 'reorder-card', null), null);
        $manager->publish($manager->create($store, 'ty-survey', 'choice-list', null), null);

        $publisher = app(StorefrontPublisher::class);
        $account = $publisher->accountPayload($store, 'https://vantora.example/');
        $this->assertSame('https://vantora.example', $account['shop_url']);
        $this->assertEqualsCanonicalizing(['account-rewards', 'account-reorder'], array_column($account['experiences'], 'type'));
        $tiers = collect($account['experiences'])->firstWhere('type', 'account-rewards')['content']['tiers'];
        $this->assertSame(['Member', 'Silver', 'Gold'], array_column($tiers, 'name'));
        $this->assertSame(['ty-survey'], array_column($publisher->checkoutPayload($store)['experiences'], 'type'), 'Checkout blocks and account blocks stay apart.');
        $this->assertSame([], array_column($publisher->payload($store)['experiences'], 'type'));

        Http::assertSent(fn (Request $r) => str_contains($r['query'] ?? '', 'metafieldDefinitionCreate')
            && json_decode($r->body(), true)['variables']['definition']['access'] === ['customerAccount' => 'READ']);
        Http::assertSent(function (Request $r) {
            if (! str_contains($r['query'] ?? '', 'metafieldsSet')) {
                return false;
            }
            $field = collect(json_decode($r->body(), true)['variables']['metafields'])->firstWhere('key', 'account');

            return $field && $field['ownerId'] === 'gid://shopify/Shop/7' && json_decode($field['value'], true)['shop_url'] === 'https://vantora.example';
        });

        $owner = $this->member($store, 'owner');
        $editor = $this->page('/app/cro/features/customer-accounts', $owner)->assertOk()->assertJsonPath('props.editor.label', 'Open customer accounts editor')->json('props.editor.url');
        $this->assertStringContainsString('settings/checkout/editor?page=order-index', $editor);
        $builder = $this->page('/app/cro/experiences/'.$rewards->id.'/edit', $owner)->assertOk();
        $this->assertStringContainsString('customer accounts editor', implode(' ', $builder->json('props.publishHelp')));
        $this->assertSame('Tiers (by total spend)', collect($builder->json('props.fields.content'))->firstWhere('label', 'Tiers (by total spend)')['label']);
    }
}
