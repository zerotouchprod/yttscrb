<?php

declare(strict_types=1);

namespace App\Application\UseCases;

use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Domain\Entities\MediaTask;

final class TranscribeVideoHandler
{
    public function __construct(
        private readonly MediaTaskRepositoryInterface $repository,
        private readonly WorkflowDispatcherInterface $dispatcher,
    ) {
    }

    public function handle(MediaTask $task): MediaTask
    {
        $existing = $this->repository->findCompletedByVideoId($task->youtubeUrl()->videoId());

        if ($existing !== null) {
            return $existing;
        }

        $this->repository->save($task);
        $this->dispatcher->dispatch($task);

        return $task;
    }

    public function findTask(string $id): ?MediaTask
    {
        return $this->repository->findById($id);
    }
}
