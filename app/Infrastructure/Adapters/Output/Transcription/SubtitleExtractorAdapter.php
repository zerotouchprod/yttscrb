<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Transcription;

use App\Application\Ports\Output\SubtitleProviderInterface;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeAntiBotExtractionPolicy;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class SubtitleExtractorAdapter implements SubtitleProviderInterface
{
    /** @var array{subtitles: string|null, title: string|null, duration_sec: int|null}|null */
    private ?array $cachedMetadata = null;
    private bool $metadataFetched = false;

    public function __construct(
        private readonly YouTubeAntiBotExtractionPolicy $policy,
        private readonly SrtParser $srtParser = new SrtParser(),
        private readonly string $outputDir = '',
    ) {
    }

    public function extract(string $youtubeUrl): ?string
    {
        $this->fetchMetadata($youtubeUrl);

        return $this->cachedMetadata['subtitles'] ?? null;
    }

    public function extractTitle(string $youtubeUrl): ?string
    {
        $this->fetchMetadata($youtubeUrl);

        return $this->cachedMetadata['title'] ?? null;
    }

    public function extractDuration(string $youtubeUrl): ?int
    {
        $this->fetchMetadata($youtubeUrl);

        return $this->cachedMetadata['duration_sec'] ?? null;
    }

    /**
     * Parse the output of `yt-dlp --print duration` into seconds.
     *
     * @param array<int, string> $output
     */
    public function parseDurationOutput(array $output): ?int
    {
        $duration = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim($output[$i]);
            if ($line !== '') {
                $duration = $line;
                break;
            }
        }

        if ($duration === '' || ! is_numeric($duration)) {
            return null;
        }

        return (int) floor((float) $duration);
    }

    private function fetchMetadata(string $youtubeUrl): void
    {
        if ($this->metadataFetched) {
            return;
        }

        $this->metadataFetched = true;
        $this->cachedMetadata = [
            'subtitles' => null,
            'title' => null,
            'duration_sec' => null,
        ];

        $outputDir = $this->outputDir !== '' ? $this->outputDir : storage_path('app/temp/subs');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $extraArgs = [
            '--write-auto-sub',
            '--skip-download',
            '--sub-lang',
            'en',
            '--convert-subs',
            'srt',
            '--print',
            'title',
            '--print',
            'duration',
        ];

        try {
            $result = $this->policy->attempt(
                $youtubeUrl,
                $outputDir,
                'subs',
                $extraArgs,
            );

            $stdout = $result->stdout;

            // Parse title and duration from stdout
            $lines = array_map('trim', explode("\n", $stdout));
            $lines = array_values(array_filter($lines, fn (string $l) => $l !== ''));

            // Duration is the last numeric line
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                if (is_numeric($lines[$i])) {
                    $this->cachedMetadata['duration_sec'] = (int) floor((float) $lines[$i]);
                    $titleLines = array_slice($lines, 0, $i);
                    $title = implode(' ', $titleLines);
                    if ($title !== '') {
                        if (mb_strlen($title) > 500) {
                            $title = mb_substr($title, 0, 497) . '...';
                        }
                        $this->cachedMetadata['title'] = $title;
                    }
                    break;
                }
            }

            if ($this->cachedMetadata['title'] === null && $lines !== []) {
                $title = implode(' ', $lines);
                if ($title !== '' && mb_strlen($title) <= 500) {
                    $this->cachedMetadata['title'] = $title;
                }
            }

            // Look for subtitle file
            $files = glob($outputDir . '/subs*.en.srt') ?: glob($outputDir . '/subs*.en.vtt') ?: [];
            if ($files === []) {
                $files = glob($outputDir . '/subs*.srt') ?: glob($outputDir . '/subs*.vtt') ?: [];
            }

            if ($files !== []) {
                $content = file_get_contents($files[0]);
                foreach ($files as $file) {
                    unlink($file);
                }
                if ($content !== false && trim($content) !== '') {
                    $this->cachedMetadata['subtitles'] = $this->srtParser->parse($content);
                }
            }
        } catch (RuntimeException $e) {
            Log::warning('Subtitle extraction failed via policy', [
                'error' => $e->getMessage(),
                'url' => $youtubeUrl,
            ]);
        }
    }
}
