<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G27: usage insights for the team, and the optional uninstall question that feeds it. */
class UsageInsightsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
    }

    public function test_the_report_counts_stores_and_uninstall_answers(): void
    {
        $paid = $this->installedStore(['plan' => 'growth', 'installed_at' => now()->subDays(5)]);
        $gone = $this->installedStore(['shop_domain' => 'gone.myshopify.com', 'installed_at' => now()->subDays(40), 'uninstalled_at' => now()->subDays(2)]);

        $this->get(route('goodbye', ['store' => $gone->id]))->assertForbidden();
        $link = URL::temporarySignedRoute('goodbye', now()->addDay(), ['store' => $gone->id]);
        $this->get($link)->assertOk()->assertSee('Why did you leave?');
        $this->post(URL::temporarySignedRoute('goodbye.store', now()->addDay(), ['store' => $gone->id]), ['reason' => 'hard_setup', 'note' => 'Theme editor confused me'])->assertOk()->assertSee('Thank you');
        $this->assertSame('hard_setup', DB::table('uninstall_feedback')->value('reason'));

        $admin = User::forceCreate(['name' => 'Ava Team', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.usage', ['range' => 'all']))->assertOk()
            ->assertSee('Usage insights')->assertSee('Activation funnel')->assertSee('It was too hard to set up')->assertSee('Theme editor confused me');
        $csv = $this->actingAs($admin)->get(route('admin.usage.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString($paid->shop_domain.',', $csv);
        $this->assertStringContainsString('It was too hard to set up', $csv);
    }
}
