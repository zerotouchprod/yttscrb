<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\CookiesYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;

test('cookies strategy is unavailable when cookies path is null', function () {
    $strategy = new CookiesYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        cookiesPath: null,
    );

    expect($strategy->isAvailable())->toBeFalse();
});

test('cookies strategy is unavailable when cookies file does not exist', function () {
    $strategy = new CookiesYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        cookiesPath: '/nonexistent/path/cookies.txt',
    );

    expect($strategy->isAvailable())->toBeFalse();
});

test('cookies strategy is available when cookies file exists', function () {
    $tempFile = sys_get_temp_dir() . '/test-cookies-' . uniqid() . '.txt';
    file_put_contents($tempFile, '# Netscape HTTP Cookie File');

    try {
        $strategy = new CookiesYtDlpStrategy(
            runner: new YtDlpProcessRunner(),
            classifier: new YouTubeExtractionErrorClassifier(),
            rateLimiter: new YtDlpRateLimiter(),
            cookiesPath: $tempFile,
        );

        expect($strategy->isAvailable())->toBeTrue();
        expect($strategy->name())->toBe('cookies');
    } finally {
        unlink($tempFile);
    }
});
