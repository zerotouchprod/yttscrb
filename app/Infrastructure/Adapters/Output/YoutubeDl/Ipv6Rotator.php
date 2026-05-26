<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

/**
 * Generates random IPv6 addresses from a /64 subnet for YouTube anti-bot rotation.
 *
 * Usage:
 *   1. Get a /64 IPv6 subnet from your hosting provider (Hetzner, DigitalOcean)
 *   2. Configure ndppd on the server to accept traffic on any address in the subnet
 *   3. Set SERVICES_YOUTUBE_IPV6_PREFIX in .env (e.g. "2a01:4f8:1c1b:1234")
 *
 * Each yt-dlp call will bind to a random address from your /64 subnet,
 * making YouTube see each request as coming from a different IP.
 *
 * IPv6 /64 subnet = 2^64 addresses — effectively unlimited.
 */
final class Ipv6Rotator
{
    /**
     * Generate a random IPv6 address from a /64 prefix.
     *
     * @param string $prefix Base prefix without trailing colons (e.g. "2a01:4f8:1c1b:1234")
     */
    public function getRandomIp(string $prefix): string
    {
        $prefix = rtrim($prefix, ':');

        // Generate 4 random 16-bit blocks for the host portion
        $suffix = sprintf(
            '%x:%x:%x:%x',
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 65535),
        );

        return $prefix . ':' . $suffix;
    }

    /**
     * Build the --source-address argument for yt-dlp if IPv6 rotation is configured.
     *
     * @return array<int, string> Empty array or ['--source-address', '<random_ip>']
     */
    public function buildYtDlpArgs(?string $prefix): array
    {
        if ($prefix === null || $prefix === '') {
            return [];
        }

        return ['--source-address', $this->getRandomIp($prefix)];
    }
}
