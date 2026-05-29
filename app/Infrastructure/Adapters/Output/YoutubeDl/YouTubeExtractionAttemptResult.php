<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

final class YouTubeExtractionAttemptResult
{
    private const TYPE_SUCCESS = 'success';
    private const TYPE_BOT_DETECTED = 'bot_detected';
    private const TYPE_RATE_LIMITED = 'rate_limited';
    private const TYPE_PERMANENT = 'permanent';
    private const TYPE_RETRYABLE_FAILURE = 'retryable_failure';

    private function __construct(
        public readonly string $resultType,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int $durationMs,
        public readonly ?string $strategyName = null,
    ) {
    }

    public static function success(string $stdout, int $durationMs, ?string $strategyName = null): self
    {
        return new self(self::TYPE_SUCCESS, $stdout, '', $durationMs, $strategyName);
    }

    public static function botDetected(string $stderr, int $durationMs, ?string $strategyName = null): self
    {
        return new self(self::TYPE_BOT_DETECTED, '', $stderr, $durationMs, $strategyName);
    }

    public static function rateLimited(string $stderr, int $durationMs, ?string $strategyName = null): self
    {
        return new self(self::TYPE_RATE_LIMITED, '', $stderr, $durationMs, $strategyName);
    }

    public static function permanent(string $stderr, int $durationMs, ?string $strategyName = null): self
    {
        return new self(self::TYPE_PERMANENT, '', $stderr, $durationMs, $strategyName);
    }

    public static function retryableFailure(string $stderr, int $durationMs, ?string $strategyName = null): self
    {
        return new self(self::TYPE_RETRYABLE_FAILURE, '', $stderr, $durationMs, $strategyName);
    }

    public function isSuccess(): bool
    {
        return $this->resultType === self::TYPE_SUCCESS;
    }

    public function isFailure(): bool
    {
        return ! $this->isSuccess();
    }

    public function isBotDetected(): bool
    {
        return $this->resultType === self::TYPE_BOT_DETECTED;
    }

    public function isRateLimited(): bool
    {
        return $this->resultType === self::TYPE_RATE_LIMITED;
    }

    public function isPermanent(): bool
    {
        return $this->resultType === self::TYPE_PERMANENT;
    }

    public function isRetryable(): bool
    {
        return $this->resultType === self::TYPE_RETRYABLE_FAILURE;
    }
}
