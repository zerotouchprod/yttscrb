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
     * Check whether at least one strategy is available (configured, not disabled,
     * and not in cooldown). Used for early rejection before dispatching workflows.
     */
    public function hasAvailableStrategy(): bool
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->isAvailable() && ! $this->cooldownStore->isInCooldown($strategy->name())) {
                return true;
            }
        }

        return false;
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

        $allInCooldown = true;
        $hadBotDetection = false;

        foreach ($availableStrategies as $strategy) {
            // Skip quarantined strategies
            if ($this->cooldownStore->isInCooldown($strategy->name())) {
                Log::info('YouTube extraction: strategy in cooldown, skipping', [
                    'strategy' => $strategy->name(),
                    'cooldown_remaining_sec' => $this->cooldownStore->getCooldownRemainingSec($strategy->name()),
                ]);
                continue;
            }

            $allInCooldown = false;

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
                $hadBotDetection = true;
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

        // Last resort: all strategies were either skipped (cooldown) or tried and returned bot_detected.
        // Try the primary strategy one more time as a final attempt.
        if (($allInCooldown || $hadBotDetection) && $availableStrategies !== []) {
            $primaryStrategy = reset($availableStrategies);
            Log::warning('YouTube extraction: all strategies exhausted or quarantined, attempting primary as last resort', [
                'strategy' => $primaryStrategy->name(),
                'url' => $url,
                'context' => $context,
                'reason' => $allInCooldown ? 'all_in_cooldown' : 'bot_detected_on_all',
            ]);

            $result = $this->executeWithRetries(
                $primaryStrategy,
                $context,
                $url,
                $outputDir,
                $outputTemplate,
                $extraArgs,
            );

            if ($result->isSuccess()) {
                return $result;
            }

            if ($result->isPermanent()) {
                throw new RuntimeException($result->stderr);
            }

            // If last resort also fails with bot detection, record it for cooldown tracking
            if ($result->isBotDetected()) {
                $this->cooldownStore->recordFailure($primaryStrategy->name());
                Log::warning('YouTube extraction: last resort also returned bot detection', [
                    'strategy' => $primaryStrategy->name(),
                    'url' => $url,
                ]);
            }
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
