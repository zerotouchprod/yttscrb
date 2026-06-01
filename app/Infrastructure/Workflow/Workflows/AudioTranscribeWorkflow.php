<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Workflows;

use App\Infrastructure\Workflow\Activities\AudioDownloaderActivity;
use App\Infrastructure\Workflow\Activities\CleanupActivity;
use App\Infrastructure\Workflow\Activities\GroqTranscriberActivity;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use Generator;
use Workflow\Workflow;

use function Workflow\activity;

final class AudioTranscribeWorkflow extends Workflow
{
    /** Run on the isolated 'audio' queue so slow/bot-detected audio downloads don't block the default worker. */
    public $queue = 'audio';

    public function execute(string $taskId, string $youtubeUrl): Generator
    {
        /** @var DownloadedAudioResult $audio */
        $audio = yield activity(AudioDownloaderActivity::class, $taskId, $youtubeUrl);

        $this->addCompensation(
            fn () => activity(CleanupActivity::class, $audio->path),
        );

        try {
            /** @var WorkflowTranscriptionResult $transcription */
            $transcription = yield activity(GroqTranscriberActivity::class, $audio->path, $youtubeUrl);

            return $transcription;
        } catch (\Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
