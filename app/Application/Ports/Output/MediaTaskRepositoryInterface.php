<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\VideoId;
use Illuminate\Pagination\LengthAwarePaginator;

interface MediaTaskRepositoryInterface
{
    public function save(MediaTask $mediaTask): void;

    public function findById(string $id): ?MediaTask;

    public function findCompletedByVideoId(VideoId $videoId): ?MediaTask;

    /**
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function findAllPaginated(?string $status, int $perPage, int $page): LengthAwarePaginator;

    public function findLatestCompleted(): ?MediaTask;

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
}
