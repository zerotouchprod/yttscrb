<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\VideoId;

interface MediaTaskRepositoryInterface
{
    public function save(MediaTask $mediaTask): void;

    public function findById(string $id): ?MediaTask;

    public function findCompletedByVideoIdForUser(VideoId $videoId, string $userId): ?MediaTask;
}
