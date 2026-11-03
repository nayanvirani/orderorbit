<?php

namespace Tests\Feature;

use App\Models\Mail\EmailDelivery;
use App\Models\Mail\EmailProvider;
use App\Models\OpsAlert;
use App\Models\User;
use App\Models\WebhookReceipt;
use App\Support\EmailSettings;
use App\Support\OpsMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithShopify;
use Tests\TestCase;

/** G26: problems open one alert, emailed at most hourly, closed when the check passes again. */
class OpsAlertsTest extends TestCase
{
    use InteractsWithShopify, RefreshDatabase;

    private bool $siteUp = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopify();
        EmailSettings::save(['enabled' => true, 'strategy' => 'priority', 'from_email' => 'hello@orderorbit.space', 'from_name' => 'Growvia', 'reply_to' => '', 'team_email' => 'team@orderorbit.space']);
        EmailProvider::create(['name' => 'Resend', 'driver' => 'resend', 'credentials' => ['api_key' => 're_1'], 'priority' => 10, 'is_active' => true]);
        Http::fake(fn (Request $r) => match (true) {
            str_contains($r->url(), 'api.resend.com') => Http::response(['id' => 'rs_'.uniqid()]),
            str_ends_with($r->url(), '/up') => Http::response('', $this->siteUp ? 200 : 503),
            default => Http::response(['data' => []]),
        });
    }

    private function stuckWebhooks(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $r = WebhookReceipt::create(['webhook_id' => 'w'.$i.uniqid(), 'shop_domain' => $this->shop, 'topic' => 'orders/paid']);
            $r->forceFill(['created_at' => now()->subMinutes(20)])->save();
        }
    }

    public function test_an_alert_opens_is_emailed_once_an_hour_and_closes_itself(): void
    {
        $monitor = app(OpsMonitor::class);
        $this->assertSame([], $monitor->run());

        $this->stuckWebhooks(3);
        $this->assertSame(['webhooks'], $monitor->run());
        $this->assertSame(['webhooks'], $monitor->run(), 'Still failing five minutes later.');
        $alert = OpsAlert::sole();
        $this->assertSame([2, null], [$alert->seen, $alert->resolved_at]);
        $this->assertSame(1, EmailDelivery::where('category', 'team_alert')->where('subject', 'like', '%webhooks failed%')->count(), 'Emailed once, not every check.');

        $this->travel(61)->minutes();
        $this->stuckWebhooks(3);
        $monitor->run();
        $this->assertSame(2, EmailDelivery::where('category', 'team_alert')->count(), 'Emailed again after an hour.');

        WebhookReceipt::query()->update(['processed_at' => now()]);
        $monitor->run();
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_the_site_is_down_only_after_two_failed_checks_and_the_daily_summary_sends(): void
    {
        $monitor = app(OpsMonitor::class);
        $this->siteUp = false;
        $this->assertSame([], $monitor->run(), 'One failed check is not enough.');
        $this->assertSame(['site_down'], $monitor->run());

        $this->assertTrue($monitor->digest());
        $digest = EmailDelivery::where('subject', 'like', 'Growvia daily summary%')->sole();
        $this->assertSame('team@orderorbit.space', $digest->to_email);

        $admin = User::forceCreate(['name' => 'Ava', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true]);
        $this->actingAs($admin)->get('/admin/ops')->assertOk()->assertSee('not responding')->assertSee('team@orderorbit.space');
        $this->actingAs($admin)->post('/admin/ops/'.OpsAlert::sole()->id.'/resolve')->assertRedirect();
        $this->assertNotNull(OpsAlert::sole()->resolved_at);
    }
}
