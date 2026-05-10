<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Workflows;

use App\Infrastructure\Workflow\Activities\AiSummaryActivity;
use App\Infrastructure\Workflow\Activities\CleanupActivity;
use App\Infrastructure\Workflow\Activities\DownloadAudioActivity;
use App\Infrastructure\Workflow\Activities\GroqTranscriberActivity;
use App\Infrastructure\Workflow\Activities\PersistResultActivity;
use App\Infrastructure\Workflow\Activities\SubtitleExtractorActivity;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

final class TranscribeVideoWorkflow extends Workflow
{
    public function execute(string $taskId, string $youtubeUrl): Generator
    {
        /** @var string|null $subtitles */
        $subtitles = yield ActivityStub::make(SubtitleExtractorActivity::class, $youtubeUrl);

        if ($subtitles !== null) {
            return yield from $this->summariseAndPersist($taskId, $subtitles, 0);
        }

        /** @var DownloadedAudioResult $audio */
        $audio = yield ActivityStub::make(DownloadAudioActivity::class, $taskId, $youtubeUrl);

        try {
            /** @var WorkflowTranscriptionResult $transcription */
            $transcription = yield ActivityStub::make(GroqTranscriberActivity::class, $audio->path);

            return yield from $this->summariseAndPersist(
                $taskId,
                $transcription->text,
                $transcription->durationSec,
            );
        } finally {
            yield ActivityStub::make(CleanupActivity::class, $audio->path);
        }
    }

    private function summariseAndPersist(string $taskId, string $transcript, int $durationSec): Generator
    {
        /** @var string|null $summary */
        $summary = yield ActivityStub::make(AiSummaryActivity::class, $transcript);

        yield ActivityStub::make(
            PersistResultActivity::class,
            $taskId,
            $transcript,
            $summary,
            $durationSec,
        );

        return null;
    }
}
