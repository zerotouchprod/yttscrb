<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\VideoId;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

interface MediaTaskRepositoryInterface
{
    public function save(MediaTask $mediaTask): void;

    public function findById(string $id): ?MediaTask;

    public function findBySlug(string $slug): ?MediaTask;

    public function findCompletedByVideoId(VideoId $videoId): ?MediaTask;

    /**
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function findAllPaginated(?string $status, int $perPage, int $page): LengthAwarePaginator;

    public function findLatestCompleted(): ?MediaTask;

    /**
     * Count completed transcriptions since the given date (inclusive).
     * Used for free tier quota enforcement (10 completed/month).
     */
    public function countCompletedSince(DateTimeImmutable $since): int;

    /**
     * Store intermediate transcript text for the given task.
     * Used by workflow activities to avoid passing large text via Redis-serialized arguments.
     */
    public function storeTranscript(string $taskId, string $transcript): void;

    /**
     * Retrieve intermediate transcript text for the given task.
     * Returns null if not yet stored.
     */
    public function getTranscript(string $taskId): ?string;

    /**
     * Store the video title for the given task.
     * Call site should guard against null before calling.
     */
    public function storeTitle(string $taskId, string $title): void;

    /**
     * Returns a lazy cursor of [slug, completed_at, updated_at] arrays
     * for all public (completed, not DMCA-removed, slug set) tasks.
     * Ordered newest-first. Used exclusively by the sitemap generator.
     *
     * @return LazyCollection<int, array{slug: string, completed_at: string|null, updated_at: string|null}>
     */
    public function findPublicSlugs(): LazyCollection;

    /**
     * Search completed (non-DMCA) tasks by title (case-insensitive partial match).
     * Uses PostgreSQL ILIKE with pg_trgm GIN index for performance.
     *
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator;

    /**
     * Public-facing paginated listing for the main page.
     * Filters: status=completed, dmca not removed, title present.
     * Ordered newest-first.
     *
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function findPublicCompletedPaginated(int $perPage, int $page): LengthAwarePaginator;
}
