<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

use Illuminate\Support\Facades\Redis;

final class StrategyCooldownStore
{
    private const KEY_PREFIX = 'youtube-extractor:strategy';

    public function __construct(
        private readonly int $failureThreshold = 3,
        private readonly int $cooldownDurationSec = 600,
        private readonly int $failureWindowSec = 120,
    ) {
    }

    /**
     * Record a failure for a strategy. If threshold reached within the window, enter cooldown.
     */
    public function recordFailure(string $strategyName): void
    {
        $now = time();
        $windowKey = $this->windowKey($strategyName);
        $cooldownKey = $this->cooldownKey($strategyName);

        // Add current timestamp to the sorted set
        Redis::zadd($windowKey, $now, (string) $now);
        // Set TTL on the window key to auto-cleanup
        Redis::expire($windowKey, $this->failureWindowSec + 60);

        // Count failures within the window
        $windowStart = $now - $this->failureWindowSec;
        $failureCount = Redis::zcount($windowKey, $windowStart, $now);

        if ($failureCount >= $this->failureThreshold) {
            Redis::set($cooldownKey, (string) ($now + $this->cooldownDurationSec));
            Redis::expire($cooldownKey, $this->cooldownDurationSec + 60);
            // Clean up the window after triggering cooldown
            Redis::del($windowKey);
        }
    }

    /**
     * Check if a strategy is currently in cooldown.
     */
    public function isInCooldown(string $strategyName): bool
    {
        return $this->getCooldownRemainingSec($strategyName) > 0;
    }

    /**
     * Get remaining cooldown seconds for a strategy. 0 if not in cooldown.
     */
    public function getCooldownRemainingSec(string $strategyName): int
    {
        $cooldownKey = $this->cooldownKey($strategyName);
        $cooldownUntil = (int) Redis::get($cooldownKey);

        if ($cooldownUntil <= 0) {
            return 0;
        }

        $remaining = $cooldownUntil - time();

        return max(0, $remaining);
    }

    /**
     * Manually reset cooldown for a strategy.
     */
    public function reset(string $strategyName): void
    {
        Redis::del($this->cooldownKey($strategyName));
        Redis::del($this->windowKey($strategyName));
    }

    private function cooldownKey(string $strategyName): string
    {
        return self::KEY_PREFIX . ':' . $strategyName . ':cooldown_until';
    }

    private function windowKey(string $strategyName): string
    {
        return self::KEY_PREFIX . ':' . $strategyName . ':failure_window';
    }
}
