<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\TranscriptionProviderInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use Illuminate\Container\Container;
use Workflow\Activity;

final class GroqTranscriberActivity extends Activity
{
    public function execute(string $audioPath): WorkflowTranscriptionResult
    {
        /** @var TranscriptionProviderInterface $provider */
        $provider = Container::getInstance()->make(TranscriptionProviderInterface::class);

        $result = $provider->transcribe(new AudioFile($audioPath));

        return new WorkflowTranscriptionResult(
            $result->text()->value(),
            $result->durationSec(),
        );
    }
}
