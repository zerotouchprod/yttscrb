<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Workflow;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Domain\Entities\MediaTask;
use App\Infrastructure\Workflow\WorkflowStarter;
use App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow;

final class WorkflowDispatcher implements WorkflowDispatcherInterface
{
    public function __construct(
        private readonly WorkflowStarter $workflowStarter,
        private readonly MediaTaskRepositoryInterface $repository,
    ) {
    }

    public function dispatch(MediaTask $task): void
    {
        $workflowId = 'transcribe-' . $task->id();

        $task->startProcessing($workflowId);
        $this->repository->save($task);

        $this->workflowStarter->start(
            TranscribeVideoWorkflow::class,
            $workflowId,
            [
                'taskId' => $task->id(),
                'youtubeUrl' => $task->youtubeUrl()->value(),
            ],
        );
    }
}
