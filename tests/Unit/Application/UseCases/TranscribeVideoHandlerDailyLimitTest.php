<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCases;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

final class TranscribeVideoHandlerDailyLimitTest extends TestCase
{
    public function testCountCompletedTodayDelegatesToRepositoryWithTodayMidnight(): void
    {
        $repository = new class () implements MediaTaskRepositoryInterface {
            public DateTimeImmutable $receivedSince;

            public function countCompletedSince(DateTimeImmutable $since, ?string $userIdentifier = null): int
            {
                $this->receivedSince = $since;

                return 3;
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
            public function findPublicSlugs(): LazyCollection
            {
                return LazyCollection::make([]);
            }
            public function searchByTitle(string $query, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator(collect(), 0, 15, 1);
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
        };

        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public function dispatch(MediaTask $task): void
            {
            }
        };

        $handler = new TranscribeVideoHandler($repository, $dispatcher);

        $result = $handler->countCompletedToday();

        self::assertSame(3, $result);
        self::assertSame(
            (new DateTimeImmutable('today 00:00:00'))->format('Y-m-d H:i:s'),
            $repository->receivedSince->format('Y-m-d H:i:s'),
        );
    }
}
