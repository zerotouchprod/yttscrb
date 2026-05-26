<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Application\Ports\Output\TranscriptionProviderInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use Illuminate\Container\Container;
use Workflow\Activity;

final class GroqTranscriberActivity extends Activity
{
    /** @var int 960s = 15 min curl timeout (900s) + 60s buffer for upload/compression + re-download */
    public $timeout = 1020;

    public function execute(string $audioPath, string $youtubeUrl): WorkflowTranscriptionResult
    {
        $this->heartbeat();

        // Pod restarts wipe /tmp, so the audio file may not exist.
        // Re-download if necessary to make the workflow resilient to restarts.
        if (! file_exists($audioPath) || ! is_readable($audioPath)) {
            /** @var AudioExtractorInterface $extractor */
            $extractor = Container::getInstance()->make(AudioExtractorInterface::class);
            $audioFile = $extractor->extract(new YouTubeUrl($youtubeUrl));
            $audioPath = $audioFile->path();
        }

        /** @var TranscriptionProviderInterface $provider */
        $provider = Container::getInstance()->make(TranscriptionProviderInterface::class);

        $result = $provider->transcribe(new AudioFile($audioPath));

        return new WorkflowTranscriptionResult(
            $result->text()->value(),
            $result->durationSec(),
        );
    }
}
