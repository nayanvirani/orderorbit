<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Internal Admin › Ops alerts shows the servers' outgoing IPs for Shopify's IP allowlist. */
class OutboundIpTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_worker_records_its_ips_and_the_ops_page_shows_both_services(): void
    {
        Http::fake(['api4.ipify.org' => Http::response('52.8.59.65'), 'api6.ipify.org' => Http::response('2600:1f1c:abc::12')]);
        $this->artisan('orderorbit:outbound-ip')->expectsOutput('IPv4: 52.8.59.65 · IPv6: 2600:1f1c:abc::12')->assertSuccessful();

        $admin = User::forceCreate(['name' => 'Ava', 'email' => 'ava@orderorbit.space', 'password' => 'secret-password-123', 'is_admin' => true, 'admin_role' => 'super_admin']);
        $this->actingAs($admin)->get('/admin/ops')->assertOk()->assertSee('Outgoing IP addresses')
            ->assertSee('Worker (orderorbit-worker)')->assertSee('52.8.59.65')->assertSee('2600:1f1c:abc::12');
    }

    public function test_a_server_without_ipv6_says_so(): void
    {
        Http::fake(['api4.ipify.org' => Http::response('52.8.59.65'), 'api6.ipify.org' => Http::response('', 500)]);
        $this->artisan('orderorbit:outbound-ip')->expectsOutput('IPv4: 52.8.59.65 · IPv6: none')->assertSuccessful();
    }
}
