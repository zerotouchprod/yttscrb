<?php

declare(strict_types=1);

namespace Tests\Feature\Unit\Application\UseCases;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\VideoId;
use App\Domain\ValueObjects\YouTubeUrl;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

final class TranscribeVideoHandlerTest extends TestCase
{
    public function testHandlerReturnsExistingCompletedTaskInsteadOfDispatchingDuplicate(): void
    {
        $existingTask = MediaTask::create(
            'existing-task',
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        );
        $existingTask->startProcessing('transcribe-existing-task');
        $existingTask->complete('Existing transcript', 'Existing summary', 10);

        $repository = new class ($existingTask) implements MediaTaskRepositoryInterface {
            public int $saveCalls = 0;

            public function __construct(private readonly MediaTask $existingTask)
            {
            }

            public function save(MediaTask $mediaTask): void
            {
                $this->saveCalls++;
            }

            public function findById(string $id): ?MediaTask
            {
                return $id === $this->existingTask->id() ? $this->existingTask : null;
            }

            public function findCompletedByVideoId(VideoId $videoId): ?MediaTask
            {
                return $videoId->value() === $this->existingTask->youtubeUrl()->videoId()->value()
                    ? $this->existingTask
                    : null;
            }

            public function findAllPaginated(?string $status, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, $perPage, $page);
            }

            public function findLatestCompleted(): ?MediaTask
            {
                return null;
            }
        };

        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public int $dispatchCalls = 0;

            public function dispatch(MediaTask $task): void
            {
                $this->dispatchCalls++;
            }
        };

        $handler = new TranscribeVideoHandler($repository, $dispatcher);

        $result = $handler->handle(
            MediaTask::create('new-task', new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
        );

        self::assertSame('existing-task', $result->id());
        self::assertSame(0, $repository->saveCalls);
        self::assertSame(0, $dispatcher->dispatchCalls);
    }

    public function testHandlerSavesAndDispatchesANewTranscriptionTask(): void
    {
        $repository = new class () implements MediaTaskRepositoryInterface {
            public int $saveCalls = 0;
            public ?MediaTask $lastSavedTask = null;

            public function save(MediaTask $mediaTask): void
            {
                $this->saveCalls++;
                $this->lastSavedTask = $mediaTask;
            }

            public function findById(string $id): ?MediaTask
            {
                return null;
            }

            public function findCompletedByVideoId(VideoId $videoId): ?MediaTask
            {
                return null;
            }

            public function findAllPaginated(?string $status, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, $perPage, $page);
            }

            public function findLatestCompleted(): ?MediaTask
            {
                return null;
            }
        };

        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public ?MediaTask $dispatchedTask = null;

            public function dispatch(MediaTask $task): void
            {
                $this->dispatchedTask = $task;
            }
        };

        $handler = new TranscribeVideoHandler($repository, $dispatcher);
        $task = MediaTask::create('new-task', new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

        $result = $handler->handle($task);

        self::assertSame('new-task', $result->id());
        self::assertSame(1, $repository->saveCalls);
        self::assertSame('new-task', $repository->lastSavedTask?->id());
        self::assertSame('new-task', $dispatcher->dispatchedTask?->id());
    }
}
