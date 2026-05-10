<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

interface WorkflowStarter
{
    /**
     * @param array<string, scalar|null> $arguments
     */
    public function start(string $workflowClass, array $arguments): int;
}
