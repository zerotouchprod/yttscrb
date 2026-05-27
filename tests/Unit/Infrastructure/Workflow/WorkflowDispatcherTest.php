<?php

declare(strict_types=1);

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\VideoId;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Output\Workflow\WorkflowDispatcher;
use App\Infrastructure\Workflow\WorkflowStarter;
use App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

it('starts durable workflow and stores returned workflow id', function (): void {
    $launcher = new class implements WorkflowStarter {
        public ?string $workflowClass = null;

        /**
         * @var array<string, scalar|null>
         */
        public array $arguments = [];

        public function start(string $workflowClass, array $arguments): int
        {
            $this->workflowClass = $workflowClass;
            $this->arguments = $arguments;

            return 42;
        }
        public function countPublicCompleted(): int
        {
            return 0;
        }
    };

    $repository = new class implements MediaTaskRepositoryInterface {
        public ?MediaTask $savedTask = null;

        public function save(MediaTask $task): void
        {
            $this->savedTask = $task;
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

        /**
         * @return LengthAwarePaginator<int, MediaTask>
         */
        public function findAllPaginated(
            ?string $status,
            int $perPage,
            int $page,
        ): LengthAwarePaginator {
            return new LengthAwarePaginator([], 0, $perPage, $page);
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

        public function searchByTitle(
            string $query,
            int $perPage,
            int $page
        ): LengthAwarePaginator {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        public function findPublicCompletedPaginated(int $perPage, int $page): LengthAwarePaginator
        {
            return new LengthAwarePaginator([], 0, $perPage, $page);
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

    $dispatcher = new WorkflowDispatcher($launcher, $repository);
    $task = MediaTask::create('task-123', new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

    $dispatcher->dispatch($task);

    expect($launcher->workflowClass)->toBe(TranscribeVideoWorkflow::class)
        ->and($launcher->arguments)->toBe([
            'taskId' => 'task-123',
            'youtubeUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->and($repository->savedTask)->not->toBeNull();

    if ($repository->savedTask !== null) {
        expect($repository->savedTask->status()->value)->toBe('processing')
            ->and($repository->savedTask->workflowId())->toBe('42');
    }
});
