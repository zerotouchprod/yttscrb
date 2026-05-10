<?php

declare(strict_types=1);

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\VideoId;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Output\Workflow\WorkflowDispatcher;
use App\Infrastructure\Workflow\WorkflowStarter;
use App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow;

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
    };

    $repository = new class implements MediaTaskRepositoryInterface {
        public ?MediaTask $savedTask = null;

        public function save(MediaTask $task): void
        {
            $this->savedTask = $task;
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

        /**
         * @return \Illuminate\Pagination\LengthAwarePaginator<int, MediaTask>
         */
        public function findAllPaginated(
            ?string $status,
            int $perPage,
            int $page,
        ): \Illuminate\Pagination\LengthAwarePaginator {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page);
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

        public function countCompletedSince(DateTimeImmutable $since): int
        {
            return 0;
        }

        public function findPublicSlugs(): \Illuminate\Support\LazyCollection
        {
            return \Illuminate\Support\LazyCollection::make([]);
        }
    };

    $dispatcher = new WorkflowDispatcher($launcher, $repository);
    $task = MediaTask::create('task-123', new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

    $dispatcher->dispatch($task);

    expect($launcher->workflowClass)->toBe(TranscribeVideoWorkflow::class)
        ->and($launcher->arguments)->toBe([
            'taskId' => 'task-123',
            'youtubeUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

    expect($repository->savedTask)->not->toBeNull();

    if ($repository->savedTask !== null) {
        expect($repository->savedTask->status()->value)->toBe('processing')
            ->and($repository->savedTask->workflowId())->toBe('42');
    }
});
