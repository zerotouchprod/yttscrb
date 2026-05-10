<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

use App\Infrastructure\Workflow\Workflows\TranscribeVideoWorkflow;
use Workflow\WorkflowStub;

final class DurableWorkflowStarter implements WorkflowStarter
{
    /**
     * @param array<string, scalar|null> $arguments
     */
    public function start(string $workflowClass, string $workflowId, array $arguments): void
    {
        $stub = WorkflowStub::make($workflowClass);
        $stub->start(
            $arguments['taskId'] ?? '',
            $arguments['youtubeUrl'] ?? '',
        );
    }
}
