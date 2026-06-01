<?php

declare(strict_types=1);

namespace Tests\Feature\Unit\Application\UseCases;

use App\Domain\ValueObjects\SummaryResult;
use App\Application\Ports\Output\ExtractionAvailabilityCheckerInterface;
use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\VideoId;
use App\Domain\ValueObjects\YouTubeUrl;
use DateTimeImmutable;
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
        $existingTask->complete('Existing transcript', new SummaryResult('Existing summary', []), 10);

        $repository = new class ($existingTask) implements MediaTaskRepositoryInterface {
            public int $saveCalls = 0;

            public function __construct(private readonly MediaTask $existingTask)
            {
            }

            public function save(MediaTask $mediaTask): void
            {
                $this->saveCalls++;
            }

            public function findByIdOrFail(string $id): \App\Domain\Entities\MediaTask
            {
                throw new \RuntimeException("Not found");
            }

            public function findById(string $id): ?MediaTask
            {
                return $id === $this->existingTask->id() ? $this->existingTask : null;
            }

            public function findBySlug(string $slug): ?MediaTask
            {
                return null;
            }

            public function findCompletedByVideoId(VideoId $videoId): ?MediaTask
            {
                return $videoId->value() === $this->existingTask->youtubeUrl()->videoId()->value()
                    ? $this->existingTask
                    : null;
            }

            public function findProcessingByVideoId(VideoId $videoId): ?MediaTask
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

            public function storeTranscript(string $taskId, string $transcript): void
            {
            }

            public function storeTitle(string $taskId, string $title): void
            {
            }

            public function getTranscript(string $taskId): ?string
            {
                return null;
            }

            public function countCompletedSince(DateTimeImmutable $since, ?string $userIdentifier = null): int
            {
                return 0;
            }

            public function findPublicSlugs(): \Illuminate\Support\LazyCollection
            {
                return \Illuminate\Support\LazyCollection::make([]);
            }

            public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, $perPage, $page);
            }

            public function findPublicCompletedPaginated(int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, $perPage, $page);
            }
            public function findCompletedWithoutTaxonomies(int $limit): array
            {
                return [];
            }
            public function findSimilar(string $taskId, int $limit = 5): array
            {
                return [];
            }
            public function saveUserIdentifier(string $taskId, string $userIdentifier): void
            {
            }
            public function existsByVideoId(string $videoId): bool
            {
                return false;
            }
            public function incrementViewCount(string $taskId): void
            {
            }
            public function findTrending(int $limit): array
            {
                return [];
            }
            public function findByIds(array $ids): array
            {
                return [];
            }
            public function countPublicCompleted(): int
            {
                return 0;
            }
        };

        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public int $dispatchCalls = 0;

            public function dispatch(MediaTask $task): void
            {
                $this->dispatchCalls++;
            }
            public function countPublicCompleted(): int
            {
                return 0;
            }
        };


        $availabilityChecker = new class () implements ExtractionAvailabilityCheckerInterface {
            public function isAnyStrategyAvailable(): bool
            {
                return true;
            }
        };
        $handler = new TranscribeVideoHandler($repository, $dispatcher, $availabilityChecker);

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

            public function findByIdOrFail(string $id): \App\Domain\Entities\MediaTask
            {
                throw new \RuntimeException("Not found");
            }

            public function findById(string $id): ?MediaTask
            {
                return null;
            }

            public function findBySlug(string $slug): ?MediaTask
            {
                return null;
            }

            public function findCompletedByVideoId(VideoId $videoId): ?MediaTask
            {
                return null;
            }

            public function findProcessingByVideoId(VideoId $videoId): ?MediaTask
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

            public function storeTranscript(string $taskId, string $transcript): void
            {
            }

            public function storeTitle(string $taskId, string $title): void
            {
            }

            public function getTranscript(string $taskId): ?string
            {
                return null;
            }

            public function countCompletedSince(DateTimeImmutable $since, ?string $userIdentifier = null): int
            {
                return 0;
            }

            public function findPublicSlugs(): \Illuminate\Support\LazyCollection
            {
                return \Illuminate\Support\LazyCollection::make([]);
            }

            public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, $perPage, $page);
            }

            public function findPublicCompletedPaginated(int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, $perPage, $page);
            }
            public function findCompletedWithoutTaxonomies(int $limit): array
            {
                return [];
            }
            public function findSimilar(string $taskId, int $limit = 5): array
            {
                return [];
            }
            public function saveUserIdentifier(string $taskId, string $userIdentifier): void
            {
            }
            public function existsByVideoId(string $videoId): bool
            {
                return false;
            }
            public function incrementViewCount(string $taskId): void
            {
            }
            public function findTrending(int $limit): array
            {
                return [];
            }
            public function findByIds(array $ids): array
            {
                return [];
            }
            public function countPublicCompleted(): int
            {
                return 0;
            }
        };

        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public ?MediaTask $dispatchedTask = null;

            public function dispatch(MediaTask $task): void
            {
                $this->dispatchedTask = $task;
            }
            public function countPublicCompleted(): int
            {
                return 0;
            }
        };


        $availabilityChecker = new class () implements ExtractionAvailabilityCheckerInterface {
            public function isAnyStrategyAvailable(): bool
            {
                return true;
            }
        };
        $handler = new TranscribeVideoHandler($repository, $dispatcher, $availabilityChecker);
        $task = MediaTask::create('new-task', new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

        $result = $handler->handle($task);

        self::assertSame('new-task', $result->id());
        self::assertSame(1, $repository->saveCalls);
        self::assertSame('new-task', $repository->lastSavedTask?->id());
        self::assertSame('new-task', $dispatcher->dispatchedTask?->id());
    }

    public function testListPublicCompletedDelegatesToRepository(): void
    {
        $expectedTask = MediaTask::create(
            'public-task',
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        );
        $expectedTask->startProcessing('wf-public');
        $expectedTask->complete('Transcript', new SummaryResult('Summary', []), 60);

        $repository = new class ($expectedTask) implements MediaTaskRepositoryInterface {
            public function __construct(private readonly MediaTask $task)
            {
            }

            public function save(MediaTask $mediaTask): void
            {
            }
            public function findByIdOrFail(string $id): \App\Domain\Entities\MediaTask
            {
                throw new \RuntimeException("Not found");
            }

            public function findById(string $id): ?MediaTask
            {
                return null;
            }
            public function findBySlug(string $slug): ?MediaTask
            {
                return null;
            }
            public function findCompletedByVideoId(VideoId $videoId): ?MediaTask
            {
                return null;
            }
            public function findProcessingByVideoId(VideoId $videoId): ?MediaTask
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
            public function storeTranscript(string $taskId, string $transcript): void
            {
            }
            public function storeTitle(string $taskId, string $title): void
            {
            }
            public function getTranscript(string $taskId): ?string
            {
                return null;
            }
            public function countCompletedSince(DateTimeImmutable $since, ?string $userIdentifier = null): int
            {
                return 0;
            }
            public function findPublicSlugs(): \Illuminate\Support\LazyCollection
            {
                return \Illuminate\Support\LazyCollection::make([]);
            }
            public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, $perPage, $page);
            }
            public function findPublicCompletedPaginated(int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect([$this->task]), 1, $perPage, $page);
            }
            public function findCompletedWithoutTaxonomies(int $limit): array
            {
                return [];
            }
            public function findSimilar(string $taskId, int $limit = 5): array
            {
                return [];
            }
            public function saveUserIdentifier(string $taskId, string $userIdentifier): void
            {
            }
            public function existsByVideoId(string $videoId): bool
            {
                return false;
            }
            public function incrementViewCount(string $taskId): void
            {
            }
            public function findTrending(int $limit): array
            {
                return [];
            }
            public function findByIds(array $ids): array
            {
                return [];
            }
            public function countPublicCompleted(): int
            {
                return 0;
            }
        };

        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public function dispatch(MediaTask $task): void
            {
            }
            public function countPublicCompleted(): int
            {
                return 0;
            }
        };


        $availabilityChecker = new class () implements ExtractionAvailabilityCheckerInterface {
            public function isAnyStrategyAvailable(): bool
            {
                return true;
            }
        };
        $handler = new TranscribeVideoHandler($repository, $dispatcher, $availabilityChecker);
        $paginator = $handler->listPublicCompleted(10, 1);

        self::assertSame(1, $paginator->total());
        self::assertSame('public-task', $paginator->getCollection()->first()?->id());
    }
}
