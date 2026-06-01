<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class PotokenYtDlpStrategy implements YouTubeExtractionStrategyInterface
{
    private const SLEEP_INTERVAL = 5;
    private const MAX_SLEEP_INTERVAL = 15;
    private const SLEEP_REQUESTS = 2;
    private const TOKEN_CACHE_KEY = 'yt_potoken';
    private const TOKEN_CACHE_TTL = 21600; // 6 hours

    public function __construct(
        private readonly YtDlpProcessRunner $runner,
        private readonly YouTubeExtractionErrorClassifier $classifier,
        private readonly YtDlpRateLimiter $rateLimiter,
        private readonly ?string $serviceUrl = null,
        private readonly string $binaryPath = 'yt-dlp',
    ) {
    }

    public function name(): string
    {
        return 'potoken';
    }

    public function isAvailable(): bool
    {
        return $this->serviceUrl !== null && $this->serviceUrl !== '';
    }

    public function execute(string $context, string $url, string $outputDir, string $outputTemplate, array $extraArgs): YouTubeExtractionAttemptResult
    {
        $token = $this->fetchToken();

        if ($token === null) {
            Log::warning('YouTube extraction: potoken unavailable, token fetch failed', [
                'service_url' => $this->serviceUrl,
            ]);

            return YouTubeExtractionAttemptResult::retryableFailure(
                'PO Token service unavailable',
                0,
                $this->name(),
            );
        }

        $extraArgsStr = implode(' ', array_map('escapeshellarg', $extraArgs));
        $extractorArgs = sprintf(
            '--extractor-args "youtube:player_client=android,ios,web;po_token=web+%s" ',
            escapeshellcmd($token),
        );
        $formatArg = $this->buildFormatArg($context);
        $outputArg = sprintf('-o %s', escapeshellarg($outputDir . '/' . $outputTemplate));

        $command = sprintf(
            '%s %s%s--no-playlist --sleep-interval %d --max-sleep-interval %d --sleep-requests %d %s %s %s',
            escapeshellcmd($this->binaryPath),
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

    /**
     * Fetch PO Token from sidecar service, cached in Redis for 6 hours.
     * Returns null if the service is unreachable or returns an invalid response.
     */
    private function fetchToken(): ?string
    {
        $serviceUrl = (string) $this->serviceUrl;

        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_CACHE_TTL, function () use ($serviceUrl): ?string {
            try {
                $response = Http::timeout(10)->get($serviceUrl . '/token');

                if (! $response->successful()) {
                    Log::warning('YouTube extraction: potoken service returned non-200', [
                        'status' => $response->status(),
                        'service_url' => $serviceUrl,
                    ]);

                    return null;
                }

                $data = $response->json();

                if (! is_array($data) || ! isset($data['token']) || ! is_string($data['token']) || $data['token'] === '') {
                    Log::warning('YouTube extraction: potoken service returned invalid response', [
                        'response_keys' => is_array($data) ? array_keys($data) : 'not_array',
                        'service_url' => $serviceUrl,
                    ]);

                    return null;
                }

                Log::info('YouTube extraction: potoken fetched successfully', [
                    'token_length' => strlen($data['token']),
                ]);

                return $data['token'];
            } catch (\Throwable $e) {
                Log::warning('YouTube extraction: potoken service unreachable', [
                    'error' => $e->getMessage(),
                    'service_url' => $serviceUrl,
                ]);

                return null;
            }
        });
    }

    private function buildFormatArg(string $context): string
    {
        if (! YouTubeExtractionContext::isAudio($context)) {
            return '';
        }

        return '-f "bestaudio[ext=m4a]/bestaudio" ';
    }
}
