<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

final class YouTubeExtractionErrorClassifier
{
    private const BENIGN_STDERR_PATTERNS = [
        'WARNING:',
    ];

    /**
     * Classify yt-dlp output into a typed result.
     */
    public function classify(
        string $stdout,
        string $stderr,
        int $exitCode,
        int $durationMs,
        string $strategyName,
    ): YouTubeExtractionAttemptResult {
        $errorOutput = $stderr !== '' ? $stderr : $stdout;

        if ($exitCode === 0 && $this->isBenignStderr($stderr)) {
            return YouTubeExtractionAttemptResult::success($stdout, $durationMs, $strategyName);
        }

        // Permanent failures — no fallback, no retry
        if ($this->matchesPermanent($errorOutput)) {
            return YouTubeExtractionAttemptResult::permanent($errorOutput, $durationMs, $strategyName);
        }

        // Rate limit — cooldown + retry same strategy
        if ($this->matchesRateLimit($errorOutput)) {
            return YouTubeExtractionAttemptResult::rateLimited($errorOutput, $durationMs, $strategyName);
        }

        // Bot detection — switch to next strategy
        if ($this->matchesBotDetection($errorOutput)) {
            return YouTubeExtractionAttemptResult::botDetected($errorOutput, $durationMs, $strategyName);
        }

        // Everything else is transient infrastructure failure — retry same strategy
        return YouTubeExtractionAttemptResult::retryableFailure($errorOutput, $durationMs, $strategyName);
    }

    private function matchesPermanent(string $output): bool
    {
        $patterns = [
            'Video unavailable',
            'Private video',
            'video is private',
            'This video is available to this channel\'s members',
            'This video is not available',
            'video has been removed',
            'removed by the uploader',
            'This video is age-restricted',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($output, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesRateLimit(string $output): bool
    {
        $patterns = [
            'HTTP Error 429',
            'Too Many Requests',
            'rate limited',
            'Cooling down',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($output, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesBotDetection(string $output): bool
    {
        $patterns = [
            'Sign in to confirm',
            'bot detection',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($output, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isBenignStderr(string $stderr): bool
    {
        if ($stderr === '') {
            return true;
        }

        $lines = preg_split('/\r?\n/', trim($stderr));
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $isBenign = false;

            foreach (self::BENIGN_STDERR_PATTERNS as $pattern) {
                if (str_contains($trimmedLine, $pattern)) {
                    $isBenign = true;
                    break;
                }
            }

            if (! $isBenign) {
                return false;
            }
        }

        return true;
    }
}
