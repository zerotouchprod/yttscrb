<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class YouTubeAntiBotExtractionPolicy
{
    /**
     * @param YouTubeExtractionStrategyInterface[] $strategies Ordered list of strategies to try.
     * @param int $maxRetriesPerStrategy Max retries for RateLimited and Retryable failures per strategy.
     * @param int $retryCooldownSec Cooldown between retries for RateLimited failures.
     * @param int $transientRetryCooldownSec Cooldown between retries for transient failures.
     */
    public function __construct(
        private readonly array $strategies,
        private readonly StrategyCooldownStore $cooldownStore,
        private readonly int $maxRetriesPerStrategy = 2,
        private readonly int $retryCooldownSec = 90,
        private readonly int $transientRetryCooldownSec = 10,
    ) {
    }

    /**
     * Attempt extraction through the strategy chain.
     *
     * @param string $url YouTube URL.
     * @param string $outputDir Directory for output files.
     * @param string $outputTemplate yt-dlp output template.
     * @param array<int, string> $extraArgs Additional yt-dlp args (e.g., --write-auto-sub).
     * @return YouTubeExtractionAttemptResult Success result.
     * @throws RuntimeException When all strategies are exhausted or permanent failure.
     */
    public function attempt(
        string $context,
        string $url,
        string $outputDir,
        string $outputTemplate,
        array $extraArgs,
    ): YouTubeExtractionAttemptResult {
        $availableStrategies = array_filter(
            $this->strategies,
            fn (YouTubeExtractionStrategyInterface $s) => $s->isAvailable(),
        );

        foreach ($availableStrategies as $strategy) {
            // Skip quarantined strategies
            if ($this->cooldownStore->isInCooldown($strategy->name())) {
                Log::info('YouTube extraction: strategy in cooldown, skipping', [
                    'strategy' => $strategy->name(),
                    'cooldown_remaining_sec' => $this->cooldownStore->getCooldownRemainingSec($strategy->name()),
                ]);
                continue;
            }

            $result = $this->executeWithRetries(
                $strategy,
                $context,
                $url,
                $outputDir,
                $outputTemplate,
                $extraArgs,
            );

            if ($result->isSuccess()) {
                return $result;
            }

            // Permanent failure — stop immediately, no fallback
            if ($result->isPermanent()) {
                throw new RuntimeException($result->stderr);
            }

            // Bot detection — record failure for cooldown and try next strategy
            if ($result->isBotDetected()) {
                $this->cooldownStore->recordFailure($strategy->name());
                Log::warning('YouTube extraction: bot detected, switching strategy', [
                    'failed_strategy' => $strategy->name(),
                    'url' => $url,
                ]);
                continue;
            }

            // Rate limited or transient — we already retried in executeWithRetries, fall through to next
            Log::warning('YouTube extraction: strategy failed after retries', [
                'strategy' => $strategy->name(),
                'result_type' => $result->resultType,
                'url' => $url,
            ]);
        }

        throw new RuntimeException(
            'All YouTube extraction strategies exhausted or quarantined. Cannot process URL.',
        );
    }

    /**
     * Execute a single strategy with internal retries for rate-limit and transient failures.
     */
    /**
     * Execute a single strategy with internal retries for rate-limit and transient failures.
     *
     * @param array<int, string> $extraArgs
     */
    private function executeWithRetries(
        YouTubeExtractionStrategyInterface $strategy,
        string $context,
        string $url,
        string $outputDir,
        string $outputTemplate,
        array $extraArgs,
    ): YouTubeExtractionAttemptResult {
        $lastResult = null;

        for ($attempt = 0; $attempt <= $this->maxRetriesPerStrategy; $attempt++) {
            $result = $strategy->execute($context, $url, $outputDir, $outputTemplate, $extraArgs);

            // Log structured attempt info
            Log::info('YouTube extraction attempt', [
                'strategy' => $strategy->name(),
                'attempt' => $attempt + 1,
                'result_type' => $result->resultType,
                'duration_ms' => $result->durationMs,
                'url' => $url,
            ]);

            // Success or permanent failure — return immediately
            if ($result->isSuccess() || $result->isPermanent()) {
                return $result;
            }

            // Bot detection — do not retry same strategy, return for fallback
            if ($result->isBotDetected()) {
                return $result;
            }

            // Rate limit — cooldown and retry
            if ($result->isRateLimited() && $attempt < $this->maxRetriesPerStrategy) {
                Log::info('YouTube extraction: rate limited, cooling down', [
                    'strategy' => $strategy->name(),
                    'cooldown_sec' => $this->retryCooldownSec * ($attempt + 1),
                    'attempt' => $attempt + 1,
                ]);
                sleep($this->retryCooldownSec * ($attempt + 1));
                $lastResult = $result;
                continue;
            }

            // Transient failure — short cooldown and retry
            if ($result->isRetryable() && $attempt < $this->maxRetriesPerStrategy) {
                Log::info('YouTube extraction: transient failure, retrying', [
                    'strategy' => $strategy->name(),
                    'cooldown_sec' => $this->transientRetryCooldownSec,
                    'attempt' => $attempt + 1,
                ]);
                sleep($this->transientRetryCooldownSec);
                $lastResult = $result;
                continue;
            }

            $lastResult = $result;
        }

        return $lastResult ?? YouTubeExtractionAttemptResult::retryableFailure(
            'Max retries exhausted',
            0,
            $strategy->name(),
        );
    }
}
