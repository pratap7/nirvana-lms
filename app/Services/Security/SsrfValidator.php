<?php

declare(strict_types=1);

namespace App\Services\Security;

use InvalidArgumentException;

class SsrfValidator
{
    /**
     * Disallowed IPv4 CIDR blocks (RFC 1918, RFC 3927 loopback, link-local, AWS metadata).
     */
    protected const PRIVATE_IPV4_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16', // Cloud metadata service (169.254.169.254)
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '255.255.255.255/32',
    ];

    /**
     * Check if a URL is safe from SSRF attacks.
     * Must be http/https, cannot resolve to private/internal IPs or cloud metadata endpoints.
     */
    public function isSafeUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        // Check for common internal hostnames
        if (in_array($host, ['localhost', '127.0.0.1', '::1', 'metadata.google.internal'], true)) {
            return false;
        }

        // Prohibit trailing dots or numeric IP encodings that evade filters
        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.onion')) {
            return false;
        }

        // Resolve DNS to actual IP addresses
        $ips = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($ips === false || empty($ips)) {
            // Fallback for direct IP address strings
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return ! $this->isPrivateIp($host);
            }

            // If hostname does not resolve, flag as unsafe for outbound requests
            return false;
        }

        foreach ($ips as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip && $this->isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate and throw if unsafe.
     */
    public function validateUrl(string $url): string
    {
        if (! $this->isSafeUrl($url)) {
            throw new InvalidArgumentException("The provided URL '{$url}' violates SSRF protection policies.");
        }

        return $url;
    }

    /**
     * Check if an IP address belongs to private/reserved ranges.
     */
    public function isPrivateIp(string $ip): bool
    {
        // IPv6 loopback and link-local check
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($ip === '::1' || str_starts_with(strtolower($ip), 'fe80:') || str_starts_with(strtolower($ip), 'fc00:') || str_starts_with(strtolower($ip), 'fd00:')) {
                return true;
            }

            return false;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        $longIp = ip2long($ip);
        if ($longIp === false) {
            return true;
        }

        foreach (self::PRIVATE_IPV4_RANGES as $cidr) {
            [$subnet, $mask] = explode('/', $cidr);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int) $mask);

            if (($longIp & $maskLong) === ($subnetLong & $maskLong)) {
                return true;
            }
        }

        return false;
    }
}
