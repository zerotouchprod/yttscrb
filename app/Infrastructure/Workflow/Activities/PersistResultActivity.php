<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use Illuminate\Container\Container;
use Workflow\Activity;

final class PersistResultActivity extends Activity
{
    public function execute(string $taskId, string $transcript, ?string $summary, int $durationSec): void
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);

        $task = $repository->findById($taskId);

        if ($task === null) {
            return;
        }

        $task->complete($transcript, $summary, $durationSec);

        $repository->save($task);
    }
}
