<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\Ipv6RotatedYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\Ipv6Rotator;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;

test('ipv6 strategy is unavailable when prefix is null', function () {
    $strategy = new Ipv6RotatedYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        ipv6Prefix: null,
        ipv6Rotator: new Ipv6Rotator(),
    );

    expect($strategy->isAvailable())->toBeFalse();
});

test('ipv6 strategy is available when prefix is configured', function () {
    $strategy = new Ipv6RotatedYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        ipv6Prefix: '2a01:4f8:1c1b:1234',
        ipv6Rotator: new Ipv6Rotator(),
    );

    expect($strategy->isAvailable())->toBeTrue();
    expect($strategy->name())->toBe('ipv6');
});
