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
        // Transition to processing BEFORE starting workflow to prevent race condition
        // where the workflow's PersistResultActivity loads the task while still pending.
        $task->startProcessing('starting');
        $this->repository->save($task);

        $workflowId = $this->workflowStarter->start(
            TranscribeVideoWorkflow::class,
            [
                'taskId' => $task->id(),
                'youtubeUrl' => $task->youtubeUrl()->value(),
            ],
        );

        // Update the real workflow ID now that the workflow has started.
        // The task is already in 'processing' state.
        $task->setWorkflowId((string) $workflowId);
        $this->repository->save($task);
    }
}
