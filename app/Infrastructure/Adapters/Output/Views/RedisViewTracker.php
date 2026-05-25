<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Views;

use App\Application\Ports\Output\ViewTrackerInterface;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

/**
 * Tracks page views using Redis for deduplication and weekly trending.
 *
 * Keys used:
 *  - "view_dedup:{ipHash}:{taskId}"  TTL 3600s  — prevents counting same IP twice per hour
 *  - "trending:weekly"               sorted set   — scores = weekly view count per task
 */
class RedisViewTracker implements ViewTrackerInterface
{
    private const DEDUP_TTL_SECONDS = 3600;
    private const WEEKLY_KEY = 'trending:weekly';

    public function __construct(
        private readonly RedisFactory $redis,
    ) {
    }

    /**
     * Returns true if this IP already viewed this task within the dedup window.
     */
    public function isRecentlyViewed(string $ipHash, string $taskId): bool
    {
        return (bool) $this->redis->connection()->exists($this->dedupKey($ipHash, $taskId));
    }

    /**
     * Mark the view as recorded (sets dedup key with TTL).
     */
    public function markViewed(string $ipHash, string $taskId): void
    {
        $this->redis->connection()->setex(
            $this->dedupKey($ipHash, $taskId),
            self::DEDUP_TTL_SECONDS,
            '1',
        );
    }

    /**
     * Increment the score of the task in the weekly sorted set.
     */
    public function recordWeeklyView(string $taskId): void
    {
        $this->redis->connection()->zincrby(self::WEEKLY_KEY, 1, $taskId);
    }

    /**
     * Return the top $limit task IDs ordered by weekly views (highest first).
     *
     * @return string[]
     */
    public function getTopTaskIds(int $limit): array
    {
        /** @var string[] $result */
        $result = $this->redis->connection()->zrevrange(self::WEEKLY_KEY, 0, $limit - 1);

        return $result;
    }

    /**
     * Returns true when the weekly sorted set has any entries.
     */
    public function hasTrendingData(): bool
    {
        return (bool) $this->redis->connection()->exists(self::WEEKLY_KEY);
    }

    /**
     * Delete the weekly sorted set (called by the weekly reset command).
     */
    public function resetWeeklyData(): void
    {
        $this->redis->connection()->del(self::WEEKLY_KEY);
    }

    private function dedupKey(string $ipHash, string $taskId): string
    {
        return "view_dedup:{$ipHash}:{$taskId}";
    }
}
