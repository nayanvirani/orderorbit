<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Support\Ticket;
use App\Models\User;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G34: a store owner asks for a custom plan; the team sees each module against their plan. */
class CustomPlanTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
    }

    public function test_a_custom_plan_request_reaches_the_team_with_modules_marked(): void
    {
        $store = $this->installedStore(['plan' => 'starter']);
        $owner = $this->member($store, 'owner');
        $this->page('/app/support', $owner)->assertJsonPath('props.customPlan', null)->assertJsonMissingPath('props.categories.custom_plan');

        FeatureFlag::create(['key' => 'premium_plan', 'mode' => 'all', 'enabled' => true]);
        Features::forget();
        $this->page('/app/support?category=custom_plan', $owner)->assertJsonPath('props.category', 'custom_plan')
            ->assertJsonPath('props.categories.custom_plan', 'Custom plan')->assertJsonPath('props.customPlan.current', 'Starter');
        $this->page('/app/settings/billing', $owner)->assertJsonPath('props.customPlans', true);

        $this->send('/app/support', ['category' => 'custom_plan', 'modules' => []], $owner)->assertStatus(422);
        $this->send('/app/support', ['category' => 'custom_plan', 'modules' => ['cart_upsells', 'checkout'], 'widgets' => 10, 'budget' => 50, 'note' => 'Holiday season'], $owner)->assertOk();
        $ticket = Ticket::sole();
        $this->assertSame('custom_plan', $ticket->category);
        $this->assertSame('Growth', $ticket->diagnostics['custom_plan']['closest']);
        $this->assertStringContainsString('Holiday season', $ticket->messages()->first()->body);

        $admin = User::forceCreate(['name' => 'Ava Team', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.ticket', $ticket->id))->assertOk()
            ->assertSee('Custom plan request')->assertSee('Included in their plan')->assertSee('Needs Growth')->assertSee('Suggest Growth')->assertSee('Best fit: suggest Growth');

        // Apply: Checkout blocks turned on for this store, runs raised, and the owner told.
        $this->actingAs($admin)->post(route('admin.ticket.custom-plan', $ticket->id), ['modules' => ['checkout'], 'runs' => 2000, 'message' => 'Checkout blocks are on for you.'])->assertRedirect();
        $store->refresh();
        $this->assertTrue($store->planIncludes('checkout'));
        $this->assertSame(2000, $store->planLimit('automation_executions'));
        $this->assertSame('Checkout blocks are on for you.', $ticket->messages()->reorder('id', 'desc')->first()->body);
    }
}
