<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\Experiences\ExperienceManager;
use App\Services\Experiences\StorefrontPublisher;
use App\Services\Experiences\TemplateLibrary;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G40: flags from the code, rolled out by store, share or schedule; emergency off for live parts. */
class FeatureFlagsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        Http::fake(fn (Request $r) => str_contains($r['query'] ?? '', 'currentAppInstallation { id } shop {')
            ? Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/1'], 'shop' => ['id' => 'gid://shopify/Shop/7', 'primaryDomain' => ['url' => 'https://example.com']]]])
            : Http::response(['data' => ['metafieldsSet' => ['userErrors' => []]]]));
    }

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Ava', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true]);
    }

    public function test_only_flags_from_the_code_can_be_set_and_they_follow_mode_share_and_schedule(): void
    {
        $a = $this->installedStore();
        $b = $this->installedStore(['shop_domain' => 'b.myshopify.com']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/flags')->assertOk()->assertSee('Profit module')->assertSee('Emergency off');
        $this->actingAs($admin)->post('/admin/flags', ['key' => 'made_up', 'mode' => 'all'])->assertSessionHasErrors('key');
        $this->actingAs($admin)->post('/admin/flags', ['key' => 'profit', 'mode' => 'stores'])->assertSessionHasErrors('store_ids');

        $this->actingAs($admin)->post('/admin/flags', ['key' => 'profit', 'mode' => 'all'])->assertRedirect();
        $this->assertTrue(Features::enabled('profit', $a) && Features::enabled('profit', $b));

        // A share of stores: stable, and growing it never drops a store that was in.
        $in = function (int $p) {
            FeatureFlag::where('key', 'profit')->update(['mode' => 'percent', 'percent' => $p]);
            Features::forget();

            return collect(range(1, 200))->filter(fn ($id) => Features::enabled('profit', tap(new \App\Models\Store, fn ($s) => $s->id = $id)))->values()->all();
        };
        $ten = $in(10);
        $fifty = $in(50);
        $this->assertGreaterThan(5, count($ten));
        $this->assertLessThan(40, count($ten));
        $this->assertSame([], array_diff($ten, $fifty));

        // Schedule: on later, off after.
        $this->actingAs($admin)->post('/admin/flags', ['key' => 'profit', 'mode' => 'all', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(3)->format('Y-m-d\TH:i')])->assertRedirect();
        $this->assertFalse(Features::enabled('profit', $a));
        $this->travel(2)->days();
        Features::forget();
        $this->assertTrue(Features::enabled('profit', $a));
        $this->travel(2)->days();
        Features::forget();
        $this->assertFalse(Features::enabled('profit', $a));
        $this->actingAs($admin)->get('/admin/flags')->assertSee('Everyone');
    }

    public function test_flags_work_from_a_cache_that_stores_serialized_values(): void
    {
        // Production caches serialize values and refuse app classes; the flags must still load from it.
        config(['cache.stores.array.serialize' => true]);
        \Illuminate\Support\Facades\Cache::forgetDriver('array');
        $store = $this->installedStore();
        FeatureFlag::create(['key' => 'live_chat', 'mode' => 'stores', 'store_ids' => [$store->id], 'enabled' => true]);
        Features::forget();

        $this->assertTrue(Features::enabled('live_chat', $store));
        $this->assertTrue(Features::enabled('live_chat', $store), 'Read back from the cache.');
        $this->assertFalse(Features::enabled('kill.sales_pop'));
    }

    public function test_emergency_off_hides_a_live_part_for_every_store_and_turning_it_back_on_restores_it(): void
    {
        app(TemplateLibrary::class)->sync();
        $store = $this->installedStore(['plan' => 'growth']);
        $manager = app(ExperienceManager::class);
        $trust = $manager->create($store, 'trust', 'trust-row', null);
        $manager->publish($trust, null);
        $publisher = app(StorefrontPublisher::class);
        $this->assertNotNull(collect($publisher->payload($store)['experiences'])->firstWhere('id', $trust->handle));
        $before = $store->entitlementsFingerprint();

        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/flags/kill/kill.storefront_widgets', ['on' => 1])->assertRedirect('/admin/flags');
        $this->assertTrue(Features::killed('storefront_widgets'));
        $this->assertSame([], $publisher->payload($store)['experiences'], 'Every storefront widget is hidden.');
        $this->assertNotSame($before, $store->fresh()->entitlementsFingerprint(), 'Every store gets republished.');
        $this->assertSame('published', $trust->fresh()->status, 'Settings are kept.');

        $this->actingAs($admin)->post('/admin/flags/kill/kill.storefront_widgets', ['on' => 0])->assertRedirect();
        $this->assertNotNull(collect($publisher->payload($store)['experiences'])->firstWhere('id', $trust->handle));

        // Automation emails wait while switched off.
        FeatureFlag::create(['key' => 'kill.automation_emails', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->artisan('orderorbit:send-emails')->expectsOutputToContain('switched off')->assertSuccessful();
    }
}
