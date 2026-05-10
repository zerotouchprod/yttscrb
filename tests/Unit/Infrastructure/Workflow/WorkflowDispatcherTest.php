<?php

declare(strict_types=1);

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Output\Workflow\WorkflowDispatcher;
use App\Infrastructure\Workflow\WorkflowStarter;
use App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow;

it('starts durable workflow with deterministic workflow id', function (): void {
    $launcher = new class implements WorkflowStarter {
        public ?string $workflowClass = null;

        public ?string $workflowId = null;

        /**
         * @var array<string, scalar|null>
         */
        public array $arguments = [];

        public function start(string $workflowClass, string $workflowId, array $arguments): void
        {
            $this->workflowClass = $workflowClass;
            $this->workflowId = $workflowId;
            $this->arguments = $arguments;
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

        public function findCompletedByVideoId(string $videoId): ?MediaTask
        {
            return null;
        }

        /**
         * @return \Illuminate\Pagination\LengthAwarePaginator<int, MediaTask>
         */
        public function findAllPaginated(?string $status, int $perPage, int $page): \Illuminate\Pagination\LengthAwarePaginator
        {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page);
        }

        public function findLatestCompleted(): ?MediaTask
        {
            return null;
        }
    };

    $dispatcher = new WorkflowDispatcher($launcher, $repository);
    $task = MediaTask::create('task-123', new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

    $dispatcher->dispatch($task);

    expect($launcher->workflowClass)->toBe(TranscribeVideoWorkflow::class)
        ->and($launcher->workflowId)->toBe('transcribe-task-123')
        ->and($launcher->arguments)->toBe([
            'taskId' => 'task-123',
            'youtubeUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

    expect($repository->savedTask)->not->toBeNull()
        ->and($repository->savedTask->status()->value)->toBe('processing')
        ->and($repository->savedTask->workflowId())->toBe('transcribe-task-123');
});
