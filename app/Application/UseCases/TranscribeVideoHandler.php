<?php

declare(strict_types=1);

namespace App\Application\UseCases;

use App\Application\Ports\Output\ExtractionAvailabilityCheckerInterface;
use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Domain\Entities\MediaTask;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class TranscribeVideoHandler
{
    public function __construct(
        private MediaTaskRepositoryInterface $repository,
        private WorkflowDispatcherInterface $dispatcher,
        private ExtractionAvailabilityCheckerInterface $availabilityChecker,
    ) {
    }

    public function handle(MediaTask $task): MediaTask
    {
        // Return already-completed task immediately (zero-cost dedup).
        $existing = $this->repository->findCompletedByVideoId($task->youtubeUrl()->videoId());

        if ($existing !== null) {
            return $existing;
        }

        // If a processing task already exists for this video, return it instead of
        // creating a second workflow that will only be discarded at persist time.
        // This prevents wasting Groq transcription and AI summary API costs on
        // duplicate submissions.
        $processing = $this->repository->findProcessingByVideoId($task->youtubeUrl()->videoId());

        if ($processing !== null) {
            return $processing;
        }

        // Early rejection: if all extraction strategies are in cooldown/quarantine,
        // fail immediately with a bot_detection error instead of dispatching a workflow
        // that would immediately fail and flood the queue with exceptions.
        if (! $this->availabilityChecker->isAnyStrategyAvailable()) {
            $task->fail('bot_detection: All extraction strategies are in cooldown/quarantine');
            $this->repository->save($task);

            return $task;
        }

        $this->repository->save($task);
        $this->dispatcher->dispatch($task);

        return $task;
    }

    public function findTask(string $id): ?MediaTask
    {
        return $this->repository->findById($id);
    }

    /**
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function listHistory(?string $status, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->repository->findAllPaginated($status, $perPage, $page);
    }

    public function findLatestCompleted(): ?MediaTask
    {
        return $this->repository->findLatestCompleted();
    }

    /**
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->repository->searchByTitle($query, $perPage, $page);
    }

    /**
     * @return LengthAwarePaginator<int, MediaTask>
     */
    public function listPublicCompleted(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->repository->findPublicCompletedPaginated($perPage, $page);
    }

    public function countCompletedToday(?string $userIdentifier = null): int
    {
        $today = new \DateTimeImmutable('today 00:00:00');

        return $this->repository->countCompletedSince($today, $userIdentifier);
    }

    public function saveUserIdentifier(string $taskId, string $userIdentifier): void
    {
        $this->repository->saveUserIdentifier($taskId, $userIdentifier);
    }
}
