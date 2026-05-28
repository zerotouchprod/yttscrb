<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Shared\Exceptions\VideoNotAvailableException;
use RuntimeException;
use Throwable;

final class YoutubeDlAudioExtractor implements AudioExtractorInterface
{
    private const OUTPUT_TEMPLATE = '%(id)s.%(ext)s';
    private const MAX_RATE_LIMIT_RETRIES = 2;
    private const RATE_LIMIT_COOLDOWN_SEC = 90;

    /** @var string|null Path to a temporary cookies file created from YT_DLP_COOKIES env var. */
    private ?string $tempCookiesFile = null;

    public function __construct(
        private readonly string $binaryPath = 'yt-dlp',
        private readonly string $outputDir = '/tmp',
        private readonly YtDlpRateLimiter $rateLimiter = new YtDlpRateLimiter(),
        private readonly ?string $ipv6Prefix = null,
        private readonly Ipv6Rotator $ipv6Rotator = new Ipv6Rotator(),
        private readonly ?string $cookiesPath = null,
    ) {
    }

    public function __destruct()
    {
        if ($this->tempCookiesFile !== null && file_exists($this->tempCookiesFile)) {
            @unlink($this->tempCookiesFile);
        }
    }

    public function extract(YouTubeUrl $youtubeUrl): AudioFile
    {
        $videoId = $youtubeUrl->videoId()->value();
        $outputPath = $this->outputDir . '/' . $videoId . '.mp3';

        if (file_exists($outputPath)) {
            return new AudioFile($outputPath);
        }

        $ipv6Args = $this->ipv6Rotator->buildYtDlpArgs($this->ipv6Prefix);
        $sourceAddr = $ipv6Args !== [] ? implode(' ', $ipv6Args) . ' ' : '';
        $cookies = $this->buildCookiesArg();
        $extractorArgs = '--extractor-args "youtube:player_client=android" ';

        $command = sprintf(
            '%s %s%s%s-x --audio-format mp3 -o %s --no-playlist --sleep-interval 5 --max-sleep-interval 15 --sleep-requests 2 %s 2>&1',
            escapeshellcmd($this->binaryPath),
            $sourceAddr,
            $cookies,
            $extractorArgs,
            escapeshellarg($this->outputDir . '/' . self::OUTPUT_TEMPLATE),
            escapeshellarg($youtubeUrl->value()),
        );

        // Try to acquire global rate limit lock before calling yt-dlp.
        // If lock is busy, throw immediately so the Activity can release back to queue.
        if (! $this->rateLimiter->tryAcquire(maxWaitSec: 5)) {
            throw new RuntimeException(
                'yt-dlp global lock is busy. Release back to queue for retry.'
            );
        }

        try {
            $this->executeCommand($command, $youtubeUrl->value());
        } finally {
            $this->rateLimiter->release();
        }

        if (file_exists($outputPath)) {
            return new AudioFile($outputPath);
        }

        throw new RuntimeException(sprintf(
            'yt-dlp completed but output file not found for %s. Expected: %s',
            $youtubeUrl->value(),
            $outputPath,
        ));
    }

    /**
     * Build the --cookies argument for yt-dlp.
     *
     * Priority:
     * 1. YT_DLP_COOKIES env var (base64-encoded Netscape cookies) — written to temp file
     * 2. cookiesPath from config (explicit file path, e.g. mounted from secret)
     */
    private function buildCookiesArg(): string
    {
        // Check YT_DLP_COOKIES env var (base64-encoded Netscape format cookies)
        $cookiesBase64 = getenv('YT_DLP_COOKIES');
        if (is_string($cookiesBase64) && $cookiesBase64 !== '') {
            if ($this->tempCookiesFile === null) {
                $cookiesContent = base64_decode($cookiesBase64, true);
                if ($cookiesContent === false) {
                    error_log('[yt-dlp] YT_DLP_COOKIES is not valid base64, ignoring.');
                } else {
                    $this->tempCookiesFile = $this->outputDir . '/cookies_' . bin2hex(random_bytes(8)) . '.txt';
                    file_put_contents($this->tempCookiesFile, $cookiesContent);
                }
            }

            if ($this->tempCookiesFile !== null && file_exists($this->tempCookiesFile)) {
                return sprintf('--cookies %s ', escapeshellarg($this->tempCookiesFile));
            }
        }

        // Fallback to cookies file path from config
        if ($this->cookiesPath !== null && $this->cookiesPath !== '' && file_exists($this->cookiesPath)) {
            return sprintf('--cookies %s ', escapeshellarg($this->cookiesPath));
        }

        return '';
    }

    private function executeCommand(string $command, string $youtubeUrl): string
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= self::MAX_RATE_LIMIT_RETRIES; $attempt++) {
            try {
                return $this->runCommand($command);
            } catch (RuntimeException $e) {
                $message = $e->getMessage();

                // Rate limit — cooldown and retry
                if (str_contains($message, 'rate limited') || str_contains($message, 'Cooling down')) {
                    $cooldown = self::RATE_LIMIT_COOLDOWN_SEC * ($attempt + 1);
                    error_log(sprintf(
                        '[yt-dlp] Rate limited for %s. Cooling down %ds (attempt %d/%d)...',
                        $youtubeUrl,
                        $cooldown,
                        $attempt + 1,
                        self::MAX_RATE_LIMIT_RETRIES + 1,
                    ));
                    sleep($cooldown);
                    $lastException = $e;
                    continue;
                }

                // Bot detection — not retryable, throw immediately
                if (str_contains($message, 'bot detection') || str_contains($message, 'Sign in to confirm')) {
                    throw $e;
                }

                // Other errors — throw immediately
                throw $e;
            }
        }

        // Exhausted retries
        throw new RuntimeException(sprintf(
            'yt-dlp rate limited after %d retries for %s. Last error: %s',
            self::MAX_RATE_LIMIT_RETRIES + 1,
            $youtubeUrl,
            $lastException?->getMessage() ?? 'unknown',
        ));
    }

    private function runCommand(string $command): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start yt-dlp process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $errorOutput = $stderr ?: $stdout;

            if (str_contains($errorOutput, 'HTTP Error 429') || str_contains($errorOutput, 'Too Many Requests')) {
                throw new RuntimeException(
                    'YouTube rate limited. Cooling down for 60 seconds before retry.'
                );
            }

            if (str_contains($errorOutput, 'Sign in to confirm')) {
                throw new RuntimeException(
                    'YouTube bot detection triggered. IP may be temporarily blocked. Try again later.'
                );
            }

            if (str_contains($errorOutput, 'Video unavailable')) {
                $reason = 'This video is unavailable.';
                if (preg_match('/Video unavailable\.?\s*(.*)/i', $errorOutput, $matches)) {
                    $reason = trim($matches[1]);
                }

                throw new VideoNotAvailableException(sprintf(
                    'Cannot download video: %s',
                    $reason,
                ));
            }

            if (str_contains($errorOutput, 'Private video') || str_contains($errorOutput, 'video is private')) {
                throw new VideoNotAvailableException('Cannot download video: This is a private video.');
            }

            throw new RuntimeException(sprintf(
                'yt-dlp failed with exit code %d: %s',
                $exitCode,
                $errorOutput,
            ));
        }

        return $stdout;
    }
}
