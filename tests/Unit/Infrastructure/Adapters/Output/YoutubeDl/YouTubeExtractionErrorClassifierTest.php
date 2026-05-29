<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionAttemptResult;

test('classifies HTTP 429 as rate limited', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'HTTP Error 429: Too Many Requests', 0, 1200, 'primary');

    expect($result->isRateLimited())->toBeTrue();
    expect($result->strategyName)->toBe('primary');
    expect($result->durationMs)->toBe(1200);
});

test('classifies Too Many Requests as rate limited', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'YouTube said: Too Many Requests', 0, 800, 'primary');

    expect($result->isRateLimited())->toBeTrue();
});

test('classifies bot detection sign-in message', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', "Sign in to confirm you're not a bot", 0, 500, 'primary');

    expect($result->isBotDetected())->toBeTrue();
});

test('classifies generic bot detection message', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'bot detection triggered', 0, 500, 'primary');

    expect($result->isBotDetected())->toBeTrue();
});

test('classifies Video unavailable as permanent', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'Video unavailable. This video has been removed', 0, 300, 'primary');

    expect($result->isPermanent())->toBeTrue();
});

test('classifies Private video as permanent', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'Private video. Sign in if you\'ve been granted access', 0, 300, 'primary');

    expect($result->isPermanent())->toBeTrue();
});

test('classifies members-only as permanent', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'This video is available to this channel\'s members', 0, 300, 'primary');

    expect($result->isPermanent())->toBeTrue();
});

test('classifies connection timeout as retryable', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'Connection timed out after 30000ms', 0, 15000, 'primary');

    expect($result->isRetryable())->toBeTrue();
});

test('classifies DNS resolution failure as retryable', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'Temporary failure in name resolution', 0, 5000, 'primary');

    expect($result->isRetryable())->toBeTrue();
});

test('classifies unknown error as retryable', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('', 'Some unexpected yt-dlp crash', 1, 2000, 'primary');

    expect($result->isRetryable())->toBeTrue();
});

test('classifies success exit code 0', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify('output content', '', 0, 1000, 'primary');

    expect($result->isSuccess())->toBeTrue();
    expect($result->stdout)->toBe('output content');
});

test('treats exit code 0 with benign stderr warning as success', function () {
    $classifier = new YouTubeExtractionErrorClassifier();
    $result = $classifier->classify(
        'output content',
        'WARNING: [youtube] Falling back to generic n function search',
        0,
        1000,
        'primary',
    );

    expect($result->isSuccess())->toBeTrue();
    expect($result->stdout)->toBe('output content');
});
