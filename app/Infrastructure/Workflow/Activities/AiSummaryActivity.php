<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\TranscriptionText;
use Illuminate\Container\Container;
use Workflow\Activity;

final class AiSummaryActivity extends Activity
{
    /** @var int */
    public $tries = 3;

    public function execute(string $taskId): ?string
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);

        $transcript = $repository->getTranscript($taskId);

        if ($transcript === null || trim($transcript) === '') {
            // PersistResultActivity will handle missing/empty transcript via $task->fail().
            return null;
        }

        $task = $repository->findById($taskId);
        $title = $task?->title();

        /** @var SummaryProviderInterface $provider */
        $provider = Container::getInstance()->make(SummaryProviderInterface::class);

        $result = $provider->summarize(
            new TranscriptionText($transcript),
            new SummaryOptions(videoTitle: $title),
        );

        return json_encode($result->toArray(), JSON_THROW_ON_ERROR);
    }
}
