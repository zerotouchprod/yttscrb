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
}
