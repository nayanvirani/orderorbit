<?php

namespace Tests\Feature;

use App\Models\FinanceEntry;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Finance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Finance (Internal Admin): monthly revenue from subscriptions and income, expenses from fixed
 * monthly costs, percentage fees and manual entries, and the profit.
 */
class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 12:00:00');
    }

    private function admin(string $role = 'super_admin'): User
    {
        return User::forceCreate(['name' => 'Ava', 'email' => $role.'@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => $role]);
    }

    private function subscription(string $shop, float $price, string $activated, ?string $trialEnds = null, ?string $cancelled = null, bool $test = false): void
    {
        $store = Store::create(['shop_domain' => $shop, 'access_token' => 'x', 'installed_at' => $activated, 'plan' => 'growth']);
        Subscription::create(['store_id' => $store->id, 'plan' => 'growth', 'shopify_subscription_id' => 'gid://shopify/AppSubscription/'.$store->id, 'status' => $cancelled ? 'CANCELLED' : 'ACTIVE',
            'price' => $price, 'test' => $test, 'activated_at' => $activated, 'trial_ends_at' => $trialEnds, 'cancelled_at' => $cancelled]);
    }

    public function test_monthly_revenue_expenses_and_profit(): void
    {
        $this->subscription('a.myshopify.com', 39.99, '2026-08-01');                              // billed Aug, Sep, Oct
        $this->subscription('b.myshopify.com', 14.99, '2026-09-20', trialEnds: '2026-10-04');     // trial in Sep: billed from Oct
        $this->subscription('c.myshopify.com', 79.99, '2026-07-01', cancelled: '2026-09-10');     // billed Jul–Sep
        $this->subscription('d.myshopify.com', 39.99, '2026-10-01', trialEnds: '2026-10-15', cancelled: '2026-10-10'); // cancelled in trial: never
        $this->subscription('e.myshopify.com', 39.99, '2026-08-01', test: true);                  // test charge: never
        config(['shopify.test_shops' => ['f.myshopify.com']]);
        $this->subscription('f.myshopify.com', 39.99, '2026-08-01');                              // test store: never

        Finance::save([['name' => 'Railway', 'amount' => 5, 'from' => '2026-09', 'until' => null], ['name' => 'Old tool', 'amount' => 10, 'from' => '2026-01', 'until' => '2026-09']], [['name' => 'Shopify processing fee', 'percent' => 2.9]]);
        FinanceEntry::create(['kind' => 'expense', 'date' => '2026-10-03', 'description' => 'Domain renewal', 'amount' => 12.50]);
        FinanceEntry::create(['kind' => 'income', 'date' => '2026-10-20', 'description' => 'Custom setup', 'amount' => 100]);
        FinanceEntry::create(['kind' => 'expense', 'date' => '2026-09-30', 'description' => 'September ad', 'amount' => 20]);

        $oct = Finance::month('2026-10');
        $this->assertSame(2, $oct['subscribers']);
        $this->assertSame(54.98, $oct['subscriptions']);
        $this->assertSame(154.98, $oct['revenue']);
        $this->assertSame(['Railway'], array_column($oct['recurring'], 'name'));
        $this->assertSame(1.59, $oct['fees'][0]['amount']); // 2.9% of 54.98
        $this->assertSame(12.5, $oct['manual']);
        $this->assertSame(19.09, $oct['expenses']);          // 5 + 1.59 + 12.50
        $this->assertSame(135.89, $oct['profit']);
        $this->assertSame(87.7, $oct['margin']);

        $sep = Finance::month('2026-09');
        $this->assertSame(119.98, $sep['subscriptions']);    // a + c
        $this->assertSame(['Railway', 'Old tool'], array_column($sep['recurring'], 'name'));
        $this->assertSame(round(5 + 10 + 3.48 + 20, 2), $sep['expenses']);

        $this->assertSame(0.0, Finance::month('2026-11')['subscriptions'], 'A month that hasn\'t started has no revenue yet.');
        $this->assertCount(12, Finance::months(12));
    }

    public function test_super_admins_manage_entries_costs_and_fees(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/finance')->assertOk()->assertSee('Finance')->assertSee('Shopify processing fee')->assertSee('Railway (project base plan)');

        $this->actingAs($admin)->post('/admin/finance/entries', ['kind' => 'expense', 'date' => '2026-10-05', 'description' => 'Logo design', 'category' => 'Contractors', 'amount' => '80'])
            ->assertRedirect('/admin/finance?month=2026-10');
        $entry = FinanceEntry::firstOrFail();
        $this->actingAs($admin)->get('/admin/finance?month=2026-10')->assertSee('Logo design')->assertSee('$80.00');

        $this->actingAs($admin)->post("/admin/finance/entries/{$entry->id}", ['kind' => 'expense', 'date' => '2026-10-05', 'description' => 'Logo design (final)', 'amount' => '95'])->assertRedirect();
        $this->assertSame('95.00', $entry->fresh()->amount);
        $this->actingAs($admin)->post('/admin/finance/entries', ['kind' => 'expense', 'date' => 'nope', 'description' => '', 'amount' => '-1'])->assertSessionHasErrors(['date', 'description', 'amount']);
        $this->actingAs($admin)->post("/admin/finance/entries/{$entry->id}/delete")->assertRedirect();
        $this->assertSame(0, FinanceEntry::count());

        $this->actingAs($admin)->post('/admin/finance/settings', [
            'recurring' => [['name' => 'Railway Pro', 'amount' => '20', 'from' => '2026-10', 'until' => ''], ['name' => '', 'amount' => '9']],
            'fees' => [['name' => 'Shopify processing fee', 'percent' => '2.9'], ['name' => 'Currency conversion', 'percent' => '1.5']],
        ])->assertRedirect();
        $this->assertSame([['name' => 'Railway Pro', 'amount' => 20.0, 'from' => '2026-10', 'until' => null]], Finance::settings()['recurring']);
        $this->assertCount(2, Finance::settings()['fees']);

        // Money is for Super Admins only.
        $this->actingAs($this->admin('operations'))->get('/admin/finance')->assertForbidden();
    }
}
