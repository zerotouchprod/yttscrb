<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

/**
 * Port for tracking page views with deduplication and weekly trending rankings.
 *
 * Implementations may use Redis, database, or any other backend.
 */
interface ViewTrackerInterface
{
    /**
     * Returns true if this IP already viewed this task within the dedup window.
     */
    public function isRecentlyViewed(string $ipHash, string $taskId): bool;

    /**
     * Mark the view as recorded (sets dedup key with TTL).
     */
    public function markViewed(string $ipHash, string $taskId): void;

    /**
     * Increment the score of the task in the weekly sorted set.
     */
    public function recordWeeklyView(string $taskId): void;

    /**
     * Return the top $limit task IDs ordered by weekly views (highest first).
     *
     * @return string[]
     */
    public function getTopTaskIds(int $limit): array;

    /**
     * Returns true when the weekly data set has any entries.
     */
    public function hasTrendingData(): bool;

    /**
     * Delete the weekly data set (called by the weekly reset command).
     */
    public function resetWeeklyData(): void;
}
