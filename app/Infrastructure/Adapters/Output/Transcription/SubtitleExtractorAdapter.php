<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Transcription;

use App\Application\Ports\Output\SubtitleProviderInterface;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;
use Illuminate\Support\Facades\Log;

final class SubtitleExtractorAdapter implements SubtitleProviderInterface
{
    private const MAX_RATE_LIMIT_RETRIES = 2;
    private const RATE_LIMIT_COOLDOWN_SEC = 90;
    private const LOCK_WAIT_SEC = 30;

    /** @var array{subtitles: string|null, title: string|null, duration_sec: int|null}|null */
    private ?array $cachedMetadata = null;
    private bool $metadataFetched = false;

    public function __construct(
        private readonly SrtParser $srtParser = new SrtParser(),
        private readonly string $binaryPath = 'yt-dlp',
        private readonly YtDlpRateLimiter $rateLimiter = new YtDlpRateLimiter(),
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
     * Fetch subtitles, title, and duration in a single yt-dlp invocation.
     * Results are cached so extract/extractTitle/extractDuration don't re-fetch.
     */
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

        $outputDir = storage_path('app/temp/subs');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $binaryPath = $this->resolveBinaryPath();

        // Single yt-dlp call: print title + duration to stdout, download subs to file
        $command = sprintf(
            '%s --write-auto-sub --skip-download --sub-lang en --convert-subs srt '
            . '--print title --print duration '
            . '--sleep-interval 5 --max-sleep-interval 15 --sleep-requests 2 '
            . '--output %s %s 2>&1',
            escapeshellcmd($binaryPath),
            escapeshellarg($outputDir . '/subs'),
            escapeshellarg($youtubeUrl),
        );

        $output = $this->runYtDlp($command);

        if ($output === null) {
            return;
        }

        // Parse title and duration from stdout lines
        // yt-dlp prints: title\n (possibly multiple lines), then duration\n
        $lines = array_map('trim', $output);
        $lines = array_values(array_filter($lines, fn (string $l) => $l !== ''));

        // Duration is the last numeric line
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (is_numeric($lines[$i])) {
                $this->cachedMetadata['duration_sec'] = (int) floor((float) $lines[$i]);
                // Title is everything before the numeric line
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

        // If no numeric line found, all non-empty lines are the title
        if ($this->cachedMetadata['title'] === null && $lines !== []) {
            $title = implode(' ', $lines);
            if ($title !== '' && mb_strlen($title) <= 500) {
                $this->cachedMetadata['title'] = $title;
            }
        }

        // Look for the downloaded subtitle file
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

    private function resolveBinaryPath(): string
    {
        if ($this->binaryPath === '') {
            return 'yt-dlp';
        }

        return $this->binaryPath;
    }

    /**
     * Run a yt-dlp command with rate-limit retry logic.
     * Gracefully returns null if the global lock cannot be acquired.
     *
     * @return array<int, string>|null Command output lines, or null on failure.
     */
    private function runYtDlp(string $command): ?array
    {
        if (! $this->rateLimiter->tryAcquire(self::LOCK_WAIT_SEC)) {
            Log::info('yt-dlp global lock busy, skipping subtitle extraction');

            return null;
        }

        try {
            for ($attempt = 0; $attempt <= self::MAX_RATE_LIMIT_RETRIES; $attempt++) {
                exec($command, $output, $exitCode);

                if ($exitCode === 0) {
                    return $output;
                }

                $errorOutput = implode("\n", $output);

                if (str_contains($errorOutput, 'HTTP Error 429') || str_contains($errorOutput, 'Too Many Requests')) {
                    if ($attempt < self::MAX_RATE_LIMIT_RETRIES) {
                        $cooldown = self::RATE_LIMIT_COOLDOWN_SEC * ($attempt + 1);
                        Log::info('yt-dlp rate limited, cooling down', [
                            'attempt' => $attempt + 1,
                            'cooldown_sec' => $cooldown,
                        ]);
                        sleep($cooldown);
                        continue;
                    }

                    Log::warning('yt-dlp rate limited after all retries');

                    return null;
                }

                if (str_contains($errorOutput, 'Sign in to confirm')) {
                    Log::warning('yt-dlp bot detection triggered');

                    return null;
                }

                return null;
            }

            return null;
        } finally {
            $this->rateLimiter->release();
        }
    }
}
