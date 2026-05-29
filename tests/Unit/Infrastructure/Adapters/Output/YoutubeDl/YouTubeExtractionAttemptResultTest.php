<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionAttemptResult;

test('success result has correct properties', function () {
    $result = YouTubeExtractionAttemptResult::success('raw stdout here', 1200);

    expect($result->isSuccess())->toBeTrue();
    expect($result->isFailure())->toBeFalse();
    expect($result->stdout)->toBe('raw stdout here');
    expect($result->durationMs)->toBe(1200);
    expect($result->resultType)->toBe('success');
});

test('bot detected failure', function () {
    $result = YouTubeExtractionAttemptResult::botDetected('Sign in to confirm you are not a bot', 800);

    expect($result->isSuccess())->toBeFalse();
    expect($result->isFailure())->toBeTrue();
    expect($result->isBotDetected())->toBeTrue();
    expect($result->isRateLimited())->toBeFalse();
    expect($result->isPermanent())->toBeFalse();
    expect($result->isRetryable())->toBeFalse();
    expect($result->stderr)->toBe('Sign in to confirm you are not a bot');
    expect($result->resultType)->toBe('bot_detected');
});

test('rate limited failure', function () {
    $result = YouTubeExtractionAttemptResult::rateLimited('HTTP Error 429', 500);

    expect($result->isRateLimited())->toBeTrue();
    expect($result->resultType)->toBe('rate_limited');
});

test('permanent failure', function () {
    $result = YouTubeExtractionAttemptResult::permanent('Video unavailable. This video is private', 300);

    expect($result->isPermanent())->toBeTrue();
    expect($result->resultType)->toBe('permanent');
});

test('transient infrastructure failure', function () {
    $result = YouTubeExtractionAttemptResult::retryableFailure('Connection timed out', 15000);

    expect($result->isRetryable())->toBeTrue();
    expect($result->resultType)->toBe('retryable_failure');
});

test('strategy name is preserved', function () {
    $result = YouTubeExtractionAttemptResult::success('output', 100, 'cookies');

    expect($result->strategyName)->toBe('cookies');
});

test('strategy name defaults to null', function () {
    $result = YouTubeExtractionAttemptResult::success('output', 100);

    expect($result->strategyName)->toBeNull();
});
