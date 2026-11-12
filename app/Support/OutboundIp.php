<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The public IP addresses Growvia's servers use when they call Shopify, for Shopify's IP
 * allowlist (Dev Dashboard › Settings). The web service checks live; the worker records its own
 * every 10 minutes (orderorbit:outbound-ip), since only it runs the background jobs.
 */
class OutboundIp
{
    /** ['v4' => ?string, 'v6' => ?string, 'at' => ISO time] */
    public static function detect(): array
    {
        $ask = function (string $url): ?string {
            try {
                $ip = trim((string) Http::timeout(5)->get($url)->body());

                return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
            } catch (Throwable) {
                return null;
            }
        };

        return ['v4' => $ask('https://api4.ipify.org'), 'v6' => $ask('https://api6.ipify.org'), 'at' => now()->toIso8601String()];
    }

    public static function record(string $service): array
    {
        $ips = self::detect();
        DB::table('platform_settings')->updateOrInsert(['key' => "outbound_ip.{$service}"], ['value' => json_encode($ips), 'updated_at' => now(), 'created_at' => now()]);

        return $ips;
    }

    public static function recorded(string $service): ?array
    {
        try {
            $v = DB::table('platform_settings')->where('key', "outbound_ip.{$service}")->value('value');
        } catch (Throwable) {
            return null;
        }

        return $v ? json_decode($v, true) : null;
    }
}
