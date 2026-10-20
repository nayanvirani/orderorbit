<?php

namespace App\Support;

use RuntimeException;

/**
 * Checks a merchant-entered https address before the server calls it (automation webhooks), so
 * it can't be pointed at our own network: the host must resolve only to public addresses, on the
 * standard https port. Callers connect to the address checked here (no second DNS lookup) and
 * don't follow redirects.
 */
class PublicUrl
{
    /** @var (callable(string): list<string>)|null Test hook: host => IP addresses. */
    public static $resolver = null;

    /** Whether the address is acceptable to save (format only; the host is resolved when it's called). */
    public static function looksValid(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return preg_match('#^https://[^\s"\'<>]+$#', $url) === 1
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && in_array($parts['port'] ?? 443, [443], true)
            && $host !== '' && $host !== 'localhost' && ! str_ends_with($host, '.localhost') && ! str_ends_with($host, '.internal')
            && (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false || self::isPublic(trim($host, '[]')));
    }

    /**
     * The host and the public IP address to connect to.
     *
     * @return array{host: string, ip: string}
     *
     * @throws RuntimeException when the address isn't a public https address
     */
    public static function resolve(string $url): array
    {
        if (! self::looksValid($url)) {
            throw new RuntimeException('Use a public https:// address.');
        }
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (self::$resolver ? (self::$resolver)($host) : (gethostbynamel($host) ?: []));
        if ($ips === [] || array_filter($ips, fn ($ip) => ! self::isPublic($ip)) !== []) {
            throw new RuntimeException('This address points to a private network.');
        }

        return ['host' => $host, 'ip' => $ips[0]];
    }

    private static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
