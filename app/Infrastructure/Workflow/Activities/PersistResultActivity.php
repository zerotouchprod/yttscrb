<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Domain\ValueObjects\SummaryResult;
use Illuminate\Container\Container;
use Webmozart\Assert\Assert;
use Workflow\Activity;

final class PersistResultActivity extends Activity
{
    /** @var int */
    public $tries = 3;

    public function execute(string $taskId, ?string $summary, int $durationSec): void
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);

        $transcript = $repository->getTranscript($taskId);

        if ($transcript === null || trim($transcript) === '') {
            $task = $repository->findById($taskId);

            if ($task !== null) {
                $task->fail(
                    $transcript === null
                        ? 'Transcript not found at persist stage — sideEffect may have failed or DB was wiped during replay'
                        : 'Transcript is empty — transcription produced no usable text'
                );
                $repository->save($task);
            }

            return;
        }

        $task = $repository->findById($taskId);

        if ($task === null) {
            return;
        }

        $summaryResult = null;

        if ($summary !== null) {
            $decoded = json_decode($summary, true, 512, JSON_THROW_ON_ERROR);
            \Webmozart\Assert\Assert::isArray($decoded, 'Summary JSON must decode to an array.');
            /** @var array{introduction: string, key_points: array<int, array{timecode: string, title: string, details: string}>, conclusion?: string|null} $decoded */
            $summaryResult = SummaryResult::fromArray($decoded);
        }

        $task->complete($transcript, $summaryResult, $durationSec);

        $repository->save($task);
    }
}
