<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use Illuminate\Container\Container;
use Workflow\Activity;

final class PersistResultActivity extends Activity
{
    public function execute(string $taskId, ?string $summary, int $durationSec): void
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);

        $transcript = $repository->getTranscript($taskId);

        if ($transcript === null) {
            $task = $repository->findById($taskId);

            if ($task !== null) {
                $task->fail(
                    'Transcript not found at persist stage — sideEffect may have failed or DB was wiped during replay'
                );
                $repository->save($task);
            }

            return;
        }

        $task = $repository->findById($taskId);

        if ($task === null) {
            return;
        }

        $task->complete($transcript, $summary, $durationSec);

        $repository->save($task);
    }
}
