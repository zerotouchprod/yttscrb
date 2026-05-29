<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

final class CookiesYtDlpStrategy implements YouTubeExtractionStrategyInterface
{
    private const SLEEP_INTERVAL = 5;
    private const MAX_SLEEP_INTERVAL = 15;
    private const SLEEP_REQUESTS = 2;
    public function __construct(
        private readonly YtDlpProcessRunner $runner,
        private readonly YouTubeExtractionErrorClassifier $classifier,
        private readonly YtDlpRateLimiter $rateLimiter,
        private readonly ?string $cookiesPath = null,
        private readonly string $binaryPath = 'yt-dlp',
    ) {
    }

    public function name(): string
    {
        return 'cookies';
    }

    public function isAvailable(): bool
    {
        return $this->cookiesPath !== null
            && $this->cookiesPath !== ''
            && file_exists($this->cookiesPath);
    }

    public function execute(string $context, string $url, string $outputDir, string $outputTemplate, array $extraArgs): YouTubeExtractionAttemptResult
    {
        $extraArgsStr = implode(' ', array_map('escapeshellarg', $extraArgs));
        $extractorArgs = '--extractor-args "youtube:player_client=android,ios,web" ';
        $cookiesArg = sprintf('--cookies %s ', escapeshellarg((string) $this->cookiesPath));
        $formatArg = $this->buildFormatArg($context);
        $outputArg = sprintf('-o %s', escapeshellarg($outputDir . '/' . $outputTemplate));

        $command = sprintf(
            '%s %s%s%s--no-playlist --sleep-interval %d --max-sleep-interval %d --sleep-requests %d %s %s %s',
            escapeshellcmd($this->binaryPath),
            $cookiesArg,
            $extractorArgs,
            $formatArg,
            self::SLEEP_INTERVAL,
            self::MAX_SLEEP_INTERVAL,
            self::SLEEP_REQUESTS,
            $outputArg,
            $extraArgsStr,
            escapeshellarg($url),
        );

        if (! $this->rateLimiter->tryAcquire(maxWaitSec: 30)) {
            return YouTubeExtractionAttemptResult::retryableFailure(
                'yt-dlp global lock busy',
                0,
                $this->name(),
            );
        }

        $startMs = (int) (microtime(true) * 1000);

        try {
            $result = $this->runner->run($command);
        } finally {
            $this->rateLimiter->release();
        }

        $durationMs = (int) (microtime(true) * 1000) - $startMs;

        return $this->classifier->classify(
            $result['stdout'],
            $result['stderr'],
            $result['exitCode'],
            $durationMs,
            $this->name(),
        );
    }

    private function buildFormatArg(string $context): string
    {
        if (! YouTubeExtractionContext::isAudio($context)) {
            return '';
        }

        return '-f "bestaudio[ext=m4a]/bestaudio" ';
    }
}
