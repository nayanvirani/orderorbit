<?php

namespace Tests\Unit;

use App\Support\PublicUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Automation webhooks only call public https addresses, never our own network. */
class PublicUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        PublicUrl::$resolver = null;
        parent::tearDown();
    }

    public function test_only_public_https_addresses_on_the_standard_port_can_be_saved(): void
    {
        $this->assertTrue(PublicUrl::looksValid('https://hooks.zapier.com/hooks/catch/1/abc'));
        foreach (['http://hooks.example.com', 'https://localhost/x', 'https://api.localhost/x', 'https://postgres.railway.internal/x', 'https://127.0.0.1/x',
            'https://10.0.0.5/x', 'https://169.254.169.254/latest', 'https://[::1]/x', 'https://hooks.example.com:8443/x', 'https://user:pass@hooks.example.com/x'] as $url) {
            $this->assertFalse(PublicUrl::looksValid($url), $url);
        }
    }

    public function test_a_public_name_that_resolves_to_a_private_address_is_refused(): void
    {
        PublicUrl::$resolver = fn (string $host) => $host === 'hooks.example.com' ? ['93.184.215.14'] : ['10.1.2.3'];
        $this->assertSame(['host' => 'hooks.example.com', 'ip' => '93.184.215.14'], PublicUrl::resolve('https://hooks.example.com/oo'));

        $this->expectException(RuntimeException::class);
        PublicUrl::resolve('https://rebind.example.net/oo');
    }
}
