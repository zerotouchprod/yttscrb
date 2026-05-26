<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * Global Redis-based rate limiter for yt-dlp calls.
 *
 * Ensures only one yt-dlp process runs at a time across ALL workers,
 * with a minimum gap between calls to avoid YouTube rate limiting.
 */
final class YtDlpRateLimiter
{
    private const LOCK_KEY = 'ytdlp:global-lock';
    private const LOCK_TTL_SEC = 120;    // Max time a single yt-dlp call can take
    private const MIN_GAP_SEC = 15;       // Minimum gap between calls
    private const MAX_WAIT_SEC = 300;     // Maximum time to wait for lock
    private const POLL_INTERVAL_MS = 500; // Check every 500ms

    /**
     * Acquire the global yt-dlp lock, waiting if necessary.
     *
     * @throws RuntimeException if lock cannot be acquired within MAX_WAIT_SEC.
     */
    public function acquire(): void
    {
        $deadline = time() + self::MAX_WAIT_SEC;
        $lastGapKey = 'ytdlp:last-call';

        while (time() < $deadline) {
            // Enforce minimum gap between calls
            $lastCall = (int) Redis::get($lastGapKey);
            $gapRemaining = $lastCall + self::MIN_GAP_SEC - time();

            if ($gapRemaining > 0) {
                usleep(min($gapRemaining * 1_000_000, self::POLL_INTERVAL_MS * 1_000));
                continue;
            }

            // Try to acquire the lock
            $acquired = Redis::set(self::LOCK_KEY, (string) time(), 'EX', self::LOCK_TTL_SEC, 'NX');

            if ($acquired) {
                return;
            }

            // Lock held by another process — wait and retry
            usleep(self::POLL_INTERVAL_MS * 1_000);
        }

        throw new RuntimeException(sprintf(
            'Could not acquire yt-dlp global lock within %d seconds. Too many concurrent downloads.',
            self::MAX_WAIT_SEC,
        ));
    }

    /**
     * Release the global yt-dlp lock and record the call timestamp.
     */
    public function release(): void
    {
        Redis::set('ytdlp:last-call', (string) time());
        Redis::del(self::LOCK_KEY);
    }
}
