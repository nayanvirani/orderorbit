<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StoreUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

class EmbeddedAppTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
    }

    public function test_first_staff_member_becomes_owner_with_shopify_details(): void
    {
        $store = $this->installedStore();
        $this->fakeAssociatedUser(['id' => 42, 'first_name' => 'Ava', 'last_name' => 'Lee', 'email' => 'ava@demo.com', 'account_owner' => false]);

        $this->get('/app', $this->as(42))->assertOk()->assertSee('Dashboard');

        $user = StoreUser::where('shopify_user_id', 42)->first();
        $this->assertSame('owner', $user->role);
        $this->assertSame('Ava Lee', $user->displayName());
        $this->assertDatabaseHas('audit_logs', ['store_id' => $store->id, 'action' => 'user.joined', 'actor_id' => $user->id]);
    }

    public function test_later_staff_default_to_staff_and_shopify_account_owner_is_always_owner(): void
    {
        $store = $this->installedStore();
        $this->member($store, 'owner');

        Http::fake(["{$this->shop}/admin/oauth/access_token" => Http::sequence()
            ->push(['access_token' => 'x', 'associated_user' => ['id' => 50, 'first_name' => 'Sam', 'email' => 'sam@demo.com', 'account_owner' => false]])
            ->push(['access_token' => 'x', 'associated_user' => ['id' => 51, 'first_name' => 'Olive', 'email' => 'olive@demo.com', 'account_owner' => true]])]);

        $this->get('/app', $this->as(50))->assertOk();
        $this->assertSame('staff', StoreUser::where('shopify_user_id', 50)->value('role'));

        $this->get('/app', $this->as(51))->assertOk();
        $this->assertSame('owner', StoreUser::where('shopify_user_id', 51)->value('role'));
    }

    public function test_pending_invite_is_claimed_by_email(): void
    {
        $store = $this->installedStore();
        $this->member($store, 'owner');
        StoreUser::create(['store_id' => $store->id, 'email' => 'new.admin@demo.com', 'role' => 'admin', 'invited_at' => now()]);

        $this->fakeAssociatedUser(['id' => 77, 'first_name' => 'New', 'email' => 'New.Admin@demo.com', 'account_owner' => false]);
        $this->get('/app', $this->as(77))->assertOk();

        $user = StoreUser::where('shopify_user_id', 77)->first();
        $this->assertSame('admin', $user->role);
        $this->assertSame(1, StoreUser::where('email', 'new.admin@demo.com')->orWhere('email', 'New.Admin@demo.com')->count());
    }

    public function test_staff_cannot_open_users_or_change_the_plan(): void
    {
        $store = $this->installedStore();
        $this->member($store, 'owner');
        $staff = $this->member($store, 'staff');

        $this->get('/app/settings/users', $this->as($staff))->assertForbidden()->assertSee('You don\'t have permission to perform this action.', false);
        $this->post('/app/settings/billing', ['plan' => 'growth'], $this->as($staff))->assertForbidden();
        $this->get('/app/settings/activity', $this->as($staff))->assertForbidden();
    }

    public function test_admin_can_invite_but_not_grant_owner(): void
    {
        $store = $this->installedStore();
        $this->member($store, 'owner');
        $admin = $this->member($store, 'admin');

        $this->post('/app/settings/users', ['email' => 'helper@demo.com', 'role' => 'staff'], $this->as($admin))
            ->assertRedirectContains('notice=invited');
        $this->assertDatabaseHas('store_users', ['email' => 'helper@demo.com', 'role' => 'staff', 'shopify_user_id' => null]);

        $this->post('/app/settings/users', ['email' => 'boss@demo.com', 'role' => 'owner'], $this->as($admin))
            ->assertRedirectContains('notice=permission');
        $this->post('/app/settings/users', ['email' => 'not-an-email', 'role' => 'staff'], $this->as($admin))
            ->assertRedirectContains('notice=invalid_email');
    }

    public function test_role_safeguards(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $admin = $this->member($store, 'admin');

        // Can't change your own role.
        $this->post("/app/settings/users/{$owner->id}/role", ['role' => 'staff'], $this->as($owner))->assertRedirectContains('notice=self');
        // Admins can't touch owners.
        $this->post("/app/settings/users/{$owner->id}/role", ['role' => 'staff'], $this->as($admin))->assertRedirectContains('notice=permission');
        // The last owner can't be removed by another owner either.
        $other = $this->member($store, 'owner');
        $this->post("/app/settings/users/{$owner->id}/remove", [], $this->as($other))->assertRedirectContains('notice=access_removed');
        $this->post("/app/settings/users/{$other->id}/role", ['role' => 'admin'], $this->as($admin))->assertRedirectContains('notice=permission');

        $this->post("/app/settings/users/{$admin->id}/role", ['role' => 'staff'], $this->as($other))->assertRedirectContains('notice=role_changed');
        $this->assertSame('staff', $admin->fresh()->role);

        $log = AuditLog::where('action', 'user.role_changed')->first();
        $this->assertSame($other->id, (int) $log->actor_id);
        $this->assertSame(['from' => 'admin', 'to' => 'staff'], $log->context);
        $this->assertNotNull($log->request_id);
    }

    public function test_last_owner_cannot_be_demoted(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $pending = StoreUser::create(['store_id' => $store->id, 'email' => 'x@demo.com', 'role' => 'owner']);

        // A pending invite doesn't count as an owner.
        $this->post("/app/settings/users/{$pending->id}/role", ['role' => 'staff'], $this->as($owner))->assertRedirectContains('notice=role_changed');
        $this->assertSame(1, $store->activeOwners()->count());
    }

    public function test_removed_user_sees_access_removed(): void
    {
        $store = $this->installedStore();
        $this->member($store, 'owner');
        $gone = $this->member($store, 'staff', ['disabled_at' => now()]);

        $this->get('/app', $this->as($gone))->assertForbidden()->assertSee('Access removed');
    }

    public function test_users_from_another_store_cannot_be_changed(): void
    {
        $store = $this->installedStore();
        $owner = $this->member($store, 'owner');
        $otherStore = $this->installedStore(['shop_domain' => 'other.myshopify.com']);
        $stranger = $this->member($otherStore, 'staff');

        $this->post("/app/settings/users/{$stranger->id}/role", ['role' => 'admin'], $this->as($owner))->assertNotFound();
    }

    public function test_settings_pages_render_for_owner(): void
    {
        $store = $this->installedStore(['capabilities' => ['online_store_2' => true, 'checkout_blocks' => false, 'thank_you_blocks' => true, 'plus' => false], 'theme_name' => 'Dawn']);
        $owner = $this->member($store, 'owner');
        Http::fake(["{$this->shop}/admin/api/*" => Http::response(['data' => ['currentAppInstallation' => ['activeSubscriptions' => []]]])]);

        $this->get('/app/settings/store', $this->as($owner))->assertOk()->assertSee('Dawn')->assertSee('Requires Shopify Plus');
        $this->get('/app/settings/users', $this->as($owner))->assertOk()->assertSee('What each role can do');
        $this->get('/app/settings/billing', $this->as($owner))->assertOk()->assertSee('Automation executions this month');
        $this->get('/app/settings/activity', $this->as($owner))->assertOk();
        $this->get('/app/onboarding', $this->as($owner))->assertOk()->assertSee('Store connection');
    }

    public function test_every_response_has_a_request_id(): void
    {
        $this->get('/')->assertHeader('X-Request-Id');
        $this->get('/', ['X-Request-Id' => 'abc12345-trace'])->assertHeader('X-Request-Id', 'abc12345-trace');
    }
}
