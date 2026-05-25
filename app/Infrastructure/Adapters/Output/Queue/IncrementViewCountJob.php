<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Queue;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Asynchronously increments the views_count for a media task.
 *
 * Dispatched from PublicTranscriptController after each unique page view
 * (deduplicated by IP hash + 1-hour TTL in Redis).
 * Runs on the 'default' Horizon queue — small, fast DB update.
 */
final class IncrementViewCountJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        private readonly string $taskId,
    ) {
    }

    public function handle(MediaTaskRepositoryInterface $repository): void
    {
        $repository->incrementViewCount($this->taskId);
    }
}
