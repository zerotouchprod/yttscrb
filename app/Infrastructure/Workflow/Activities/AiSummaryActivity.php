<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\TranscriptionText;
use Illuminate\Container\Container;
use RuntimeException;
use Workflow\Activity;

final class AiSummaryActivity extends Activity
{
    public function execute(string $taskId): string
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);

        $transcript = $repository->getTranscript($taskId);

        if ($transcript === null) {
            throw new RuntimeException("Transcript not found for task: {$taskId}");
        }

        /** @var SummaryProviderInterface $provider */
        $provider = Container::getInstance()->make(SummaryProviderInterface::class);

        $result = $provider->summarize(
            new TranscriptionText($transcript),
            new SummaryOptions(),
        );

        return $result->text();
    }
}
