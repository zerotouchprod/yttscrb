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
    /** @var int 960s = 15 min curl timeout (900s) + 60s buffer for upload/compression */
    public $timeout = 960;

    public function execute(string $audioPath): WorkflowTranscriptionResult
    {
        $this->heartbeat();

        /** @var TranscriptionProviderInterface $provider */
        $provider = Container::getInstance()->make(TranscriptionProviderInterface::class);

        $result = $provider->transcribe(new AudioFile($audioPath));

        return new WorkflowTranscriptionResult(
            $result->text()->value(),
            $result->durationSec(),
        );
    }
}
