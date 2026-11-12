<?php

namespace App\Console\Commands;

use App\Support\OutboundIp;
use Illuminate\Console\Command;

/** Records the worker's public IPs for Internal Admin › Ops alerts (Shopify's IP allowlist). */
class RecordOutboundIp extends Command
{
    protected $signature = 'orderorbit:outbound-ip';

    protected $description = 'Record this server\'s outgoing IP addresses';

    public function handle(): int
    {
        $ips = OutboundIp::record('worker');
        $this->info('IPv4: '.($ips['v4'] ?? 'none').' · IPv6: '.($ips['v6'] ?? 'none'));

        return self::SUCCESS;
    }
}
