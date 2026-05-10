<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Workflows;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Infrastructure\Workflow\Activities\AiSummaryActivity;
use App\Infrastructure\Workflow\Activities\AudioDownloaderActivity;
use App\Infrastructure\Workflow\Activities\CleanupActivity;
use App\Infrastructure\Workflow\Activities\GroqTranscriberActivity;
use App\Infrastructure\Workflow\Activities\PersistResultActivity;
use App\Infrastructure\Workflow\Activities\SubtitleExtractorActivity;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use App\Infrastructure\Workflow\DTO\WorkflowTranscriptionResult;
use Generator;
use Illuminate\Container\Container;
use Throwable;
use Workflow\Workflow;

use function Workflow\activity;
use function Workflow\sideEffect;

final class TranscribeVideoWorkflow extends Workflow
{
    public function execute(string $taskId, string $youtubeUrl): Generator
    {
        /** @var array{subtitles: string|null, title: string|null} $subtitleResult */
        $subtitleResult = yield activity(SubtitleExtractorActivity::class, $youtubeUrl);

        if ($subtitleResult['subtitles'] !== null) {
            yield sideEffect(fn () => $this->storeTranscript($taskId, $subtitleResult['subtitles']));
            if ($subtitleResult['title'] !== null) {
                yield sideEffect(fn () => $this->storeTitle($taskId, $subtitleResult['title']));
            }
            return yield from $this->summariseAndPersist($taskId, 0);
        }

        try {
            /** @var DownloadedAudioResult $audio */
            $audio = yield activity(AudioDownloaderActivity::class, $taskId, $youtubeUrl);

            $this->addCompensation(
                fn () => activity(CleanupActivity::class, $audio->path),
            );

            /** @var WorkflowTranscriptionResult $transcription */
            $transcription = yield activity(GroqTranscriberActivity::class, $audio->path);
        } catch (Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }

        yield sideEffect(fn () => $this->storeTranscript($taskId, $transcription->text));

        if ($subtitleResult['title'] !== null) {
            yield sideEffect(fn () => $this->storeTitle($taskId, $subtitleResult['title']));
        }

        return yield from $this->summariseAndPersist($taskId, $transcription->durationSec);
    }

    private function summariseAndPersist(string $taskId, int $durationSec): Generator
    {
        /** @var string|null $summary */
        $summary = yield activity(AiSummaryActivity::class, $taskId);

        yield activity(
            PersistResultActivity::class,
            $taskId,
            $summary,
            $durationSec,
        );

        return null;
    }

    private function storeTranscript(string $taskId, string $transcript): void
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);
        $repository->storeTranscript($taskId, $transcript);
    }

    private function storeTitle(string $taskId, string $title): void
    {
        /** @var MediaTaskRepositoryInterface $repository */
        $repository = Container::getInstance()->make(MediaTaskRepositoryInterface::class);
        $repository->storeTitle($taskId, $title);
    }
}
