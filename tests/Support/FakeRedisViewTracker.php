<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Infrastructure\Adapters\Output\Views\RedisViewTracker;

/**
 * Test double for RedisViewTracker that avoids live Redis connections.
 * Tracks calls so assertions can be written against them.
 */
final class FakeRedisViewTracker extends RedisViewTracker
{
    public bool $markViewedCalled = false;
    public bool $recordWeeklyViewCalled = false;

    /** @var string[] */
    public array $topTaskIds = [];

    public bool $hasTrendingDataReturn = false;

    public function __construct(
        private readonly bool $isRecentlyViewed = false,
    ) {
        // Pass a null-object RedisFactory; override methods bypass it.
        parent::__construct(new NullRedisFactory());
    }

    public function isRecentlyViewed(string $ipHash, string $taskId): bool
    {
        return $this->isRecentlyViewed;
    }

    public function markViewed(string $ipHash, string $taskId): void
    {
        $this->markViewedCalled = true;
    }

    public function recordWeeklyView(string $taskId): void
    {
        $this->recordWeeklyViewCalled = true;
    }

    /**
     * @return string[]
     */
    public function getTopTaskIds(int $limit): array
    {
        return array_slice($this->topTaskIds, 0, $limit);
    }

    public function hasTrendingData(): bool
    {
        return $this->hasTrendingDataReturn;
    }

    public function resetWeeklyData(): void
    {
        $this->topTaskIds = [];
        $this->hasTrendingDataReturn = false;
    }
}
