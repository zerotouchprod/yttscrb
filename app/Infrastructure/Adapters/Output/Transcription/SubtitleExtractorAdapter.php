<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Transcription;

use App\Application\Ports\Output\SubtitleProviderInterface;
use Illuminate\Support\Facades\Log;

final class SubtitleExtractorAdapter implements SubtitleProviderInterface
{
    private const MAX_RATE_LIMIT_RETRIES = 3;
    private const RATE_LIMIT_COOLDOWN_SEC = 60;

    public function __construct(
        private readonly SrtParser $srtParser = new SrtParser(),
        private readonly string $binaryPath = 'yt-dlp',
    ) {
    }

    public function extract(string $youtubeUrl): ?string
    {
        $outputDir = storage_path('app/temp/subs');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $binaryPath = $this->resolveBinaryPath();

        $command = sprintf(
            '%s --write-auto-sub --skip-download --sub-lang en --convert-subs srt '
            . '--sleep-interval 5 --max-sleep-interval 30 --sleep-requests 1 '
            . '--output %s %s 2>&1',
            escapeshellcmd($binaryPath),
            escapeshellarg($outputDir . '/subs'),
            escapeshellarg($youtubeUrl),
        );

        $output = $this->runYtDlp($command);

        if ($output === null) {
            return null;
        }

        // Look for the downloaded subtitle file
        $files = glob($outputDir . '/subs*.en.srt') ?: glob($outputDir . '/subs*.en.vtt') ?: [];

        if ($files === []) {
            // Try without language suffix
            $files = glob($outputDir . '/subs*.srt') ?: glob($outputDir . '/subs*.vtt') ?: [];
        }

        if ($files === []) {
            return null;
        }

        $content = file_get_contents($files[0]);

        // Cleanup
        foreach ($files as $file) {
            unlink($file);
        }

        if ($content === false || trim($content) === '') {
            return null;
        }

        // Parse SRT into timecoded transcript: "[MM:SS] text" lines
        return $this->srtParser->parse($content);
    }

    public function extractTitle(string $youtubeUrl): ?string
    {
        $binaryPath = $this->resolveBinaryPath();

        $command = sprintf(
            '%s --print title --skip-download --sleep-interval 5 --max-sleep-interval 30 --sleep-requests 1 %s 2>/dev/null',
            escapeshellcmd($binaryPath),
            escapeshellarg($youtubeUrl),
        );

        $output = $this->runYtDlp($command);

        if ($output === null || $output === []) {
            return null;
        }

        // Take only the last non-empty line — the actual title
        $title = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim($output[$i]);
            if ($line !== '') {
                $title = $line;
                break;
            }
        }

        if ($title === '') {
            return null;
        }

        // Truncate to 500 chars max (safe for DB column)
        if (mb_strlen($title) > 500) {
            $title = mb_substr($title, 0, 497) . '...';
        }

        return $title;
    }

    public function extractDuration(string $youtubeUrl): ?int
    {
        $binaryPath = $this->resolveBinaryPath();

        $command = sprintf(
            '%s --print duration --skip-download --sleep-interval 5 --max-sleep-interval 30 --sleep-requests 1 %s 2>/dev/null',
            escapeshellcmd($binaryPath),
            escapeshellarg($youtubeUrl),
        );

        $output = $this->runYtDlp($command);

        if ($output === null || $output === []) {
            return null;
        }

        return $this->parseDurationOutput($output);
    }

    /**
     * Parse the output of `yt-dlp --print duration` into seconds.
     *
     * @param array<int, string> $output
     */
    public function parseDurationOutput(array $output): ?int
    {
        // Take only the last non-empty line
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
     *
     * @return array<int, string>|null Command output lines, or null on non-retryable failure.
     */
    private function runYtDlp(string $command): ?array
    {
        for ($attempt = 0; $attempt <= self::MAX_RATE_LIMIT_RETRIES; $attempt++) {
            exec($command, $output, $exitCode);

            if ($exitCode === 0) {
                return $output;
            }

            $errorOutput = implode("\n", $output);

            // Rate limit — cooldown and retry
            if (str_contains($errorOutput, 'HTTP Error 429') || str_contains($errorOutput, 'Too Many Requests')) {
                if ($attempt < self::MAX_RATE_LIMIT_RETRIES) {
                    $cooldown = self::RATE_LIMIT_COOLDOWN_SEC * ($attempt + 1);
                    Log::info('yt-dlp rate limited, cooling down', [
                        'attempt' => $attempt + 1,
                        'cooldown_sec' => $cooldown,
                        'command' => $command,
                    ]);
                    sleep($cooldown);
                    continue;
                }

                Log::warning('yt-dlp rate limited after all retries', [
                    'retries' => self::MAX_RATE_LIMIT_RETRIES + 1,
                    'command' => $command,
                ]);

                return null;
            }

            // Bot detection — not retryable
            if (str_contains($errorOutput, 'Sign in to confirm')) {
                Log::warning('yt-dlp bot detection triggered', [
                    'command' => $command,
                ]);

                return null;
            }

            // Other errors — not retryable (no subtitles, video unavailable, etc.)
            return null;
        }

        return null;
    }
}
