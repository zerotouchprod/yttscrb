<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\Entities\MediaTask;

interface WorkflowDispatcherInterface
{
    public function dispatch(MediaTask $task): void;
}
