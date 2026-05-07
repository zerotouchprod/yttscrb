<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Workflow;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\SubtitleProviderInterface;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Application\Ports\Output\TranscriptionProviderInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\TranscriptionText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessTranscriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 1800; // 30 minutes

    public function __construct(
        private readonly string $taskId,
        private readonly string $youtubeUrl,
    ) {
    }

    public function handle(
        MediaTaskRepositoryInterface $repository,
        SubtitleProviderInterface $subtitleProvider,
        TranscriptionProviderInterface $transcriptionProvider,
        SummaryProviderInterface $summaryProvider,
    ): void {
        $task = $repository->findById($this->taskId);

        if ($task === null) {
            Log::warning('ProcessTranscriptionJob: task not found', ['taskId' => $this->taskId]);

            return;
        }

        $workflowId = 'transcribe-' . $this->taskId;
        $task->startProcessing($workflowId);
        $repository->save($task);

        try {
            // Step 0: Try subtitles (zero-cost)
            $subtitles = $subtitleProvider->extract($this->youtubeUrl);

            if ($subtitles !== null) {
                $summary = $summaryProvider->summarize(
                    new TranscriptionText($subtitles),
                    new SummaryOptions(),
                );

                $task->complete($subtitles, $summary->text(), 0);
                $repository->save($task);

                return;
            }

            // Step 1: Download audio via yt-dlp
            $audioPath = $this->downloadAudio($this->youtubeUrl);

            try {
                $audioFile = new AudioFile($audioPath);

                // Step 2: Transcribe
                $transcription = $transcriptionProvider->transcribe($audioFile);

                // Step 3: Summarize
                $summary = $summaryProvider->summarize(
                    $transcription->text(),
                    new SummaryOptions(),
                );

                // Step 4: Persist
                $task->complete(
                    $transcription->text()->value(),
                    $summary->text(),
                    $transcription->durationSec(),
                );
                $repository->save($task);
            } finally {
                // Step 5: Cleanup
                if (file_exists($audioPath)) {
                    unlink($audioPath);
                }
            }
        } catch (Throwable $e) {
            Log::error('ProcessTranscriptionJob failed', [
                'taskId' => $this->taskId,
                'error' => $e->getMessage(),
            ]);

            $task->fail($e->getMessage());
            $repository->save($task);
        }
    }

    private function downloadAudio(string $youtubeUrl): string
    {
        $outputDir = storage_path('app/temp');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir . '/' . $this->taskId . '.mp3';
        $ytDlp = config('services.yt_dlp_binary', 'yt-dlp');

        if (! is_string($ytDlp) || $ytDlp === '') {
            $ytDlp = 'yt-dlp';
        }

        $command = sprintf(
            '%s -x --audio-format mp3 --audio-quality 96K -o %s %s 2>&1',
            escapeshellcmd($ytDlp),
            escapeshellarg($outputPath),
            escapeshellarg($youtubeUrl),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('yt-dlp failed: ' . implode("\n", $output));
        }

        // yt-dlp appends .mp3, so the actual file may have .mp3.mp3 or just .mp3
        $actualPath = $outputPath;
        if (! file_exists($actualPath)) {
            // Try with double extension
            $altPath = $outputPath . '.mp3';
            if (file_exists($altPath)) {
                $actualPath = $altPath;
            }
        }

        if (! file_exists($actualPath)) {
            throw new \RuntimeException('Audio file not found after download');
        }

        return $actualPath;
    }
}
