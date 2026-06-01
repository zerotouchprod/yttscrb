<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCases;

use App\Domain\ValueObjects\SummaryResult;
use App\Application\Ports\Output\ExtractionAvailabilityCheckerInterface;
use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

final class TranscribeVideoHandlerSearchTest extends TestCase
{
    public function testSearchByTitleDelegatesToRepository(): void
    {
        $expectedPaginator = new LengthAwarePaginator(
            collect([]),
            0,
            15,
            1,
        );

        $repository = new class ($expectedPaginator) implements MediaTaskRepositoryInterface {
            public string $lastQuery = '';
            public int $lastPerPage = 0;
            public int $lastPage = 0;

            /** @param LengthAwarePaginator<int, MediaTask> $paginator */
            public function __construct(private readonly LengthAwarePaginator $paginator)
            {
            }

            public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator
            {
                $this->lastQuery = $query;
                $this->lastPerPage = $perPage;
                $this->lastPage = $page;

                return $this->paginator;
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
            public function findCompletedByVideoId(\App\Domain\ValueObjects\VideoId $videoId): ?MediaTask
            {
                return null;
            }
            public function findProcessingByVideoId(\App\Domain\ValueObjects\VideoId $videoId): ?MediaTask
            {
                return null;
            }
            public function findAllPaginated(?string $status, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, 15, 1);
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
            public function findPublicSlugs(): LazyCollection
            {
                return LazyCollection::make([]);
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

        $result = $handler->searchByTitle('rick astley', 10, 2);

        self::assertSame('rick astley', $repository->lastQuery);
        self::assertSame(10, $repository->lastPerPage);
        self::assertSame(2, $repository->lastPage);
        self::assertSame($expectedPaginator, $result);
    }

    public function testSearchByTitleReturnsLengthAwarePaginator(): void
    {
        $task = MediaTask::create(
            'test-task',
            new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $task->startProcessing('wf-test');
        $task->complete('Transcript', new SummaryResult('Test intro', []), 212);
        $task->setTitle('Rick Astley - Never Gonna Give You Up');

        $paginator = new LengthAwarePaginator(
            collect([$task]),
            1,
            15,
            1,
        );

        $repository = new class ($paginator) implements MediaTaskRepositoryInterface {
            /** @param LengthAwarePaginator<int, MediaTask> $paginator */
            public function __construct(private readonly LengthAwarePaginator $paginator)
            {
            }
            public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator
            {
                return $this->paginator;
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
            public function findCompletedByVideoId(\App\Domain\ValueObjects\VideoId $videoId): ?MediaTask
            {
                return null;
            }
            public function findProcessingByVideoId(\App\Domain\ValueObjects\VideoId $videoId): ?MediaTask
            {
                return null;
            }
            public function findAllPaginated(?string $status, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, 15, 1);
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
            public function findPublicSlugs(): LazyCollection
            {
                return LazyCollection::make([]);
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

        $result = $handler->searchByTitle('rick', 15, 1);

        self::assertInstanceOf(LengthAwarePaginator::class, $result);
        $collection = $result->getCollection();
        self::assertCount(1, $collection);

        /** @var MediaTask $first */
        $first = $collection->first();
        self::assertSame('test-task', $first->id());
    }
}
