<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Workflow;

use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Domain\Entities\MediaTask;

final class WorkflowDispatcher implements WorkflowDispatcherInterface
{
    public function dispatch(MediaTask $task): void
    {
        ProcessTranscriptionJob::dispatch($task->id(), $task->youtubeUrl()->value());
    }
}
