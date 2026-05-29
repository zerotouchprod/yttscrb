<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\Ipv6RotatedYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\Ipv6Rotator;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionContext;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

uses(TestCase::class);

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

test('ipv6 strategy does not force audio format for subtitle extraction', function () {
    $binaryPath = sys_get_temp_dir() . '/fake-ytdlp-ipv6-' . uniqid('', true) . '.sh';
    file_put_contents($binaryPath, "#!/bin/sh\nset -eu\nprintf \"%s\\n\" \"$@\"\n");
    chmod($binaryPath, 0755);

    Redis::shouldReceive('get')->once()->with('ytdlp:last-call')->andReturn(null);
    Redis::shouldReceive('set')->once()->with('ytdlp:global-lock', Mockery::type('string'), 'EX', 120, 'NX')->andReturn(true);
    Redis::shouldReceive('set')->once()->with('ytdlp:last-call', Mockery::type('string'));
    Redis::shouldReceive('del')->once()->with('ytdlp:global-lock');

    try {
        $strategy = new Ipv6RotatedYtDlpStrategy(
            runner: new YtDlpProcessRunner(),
            classifier: new YouTubeExtractionErrorClassifier(),
            rateLimiter: new YtDlpRateLimiter(),
            ipv6Prefix: '2a01:4f8:1c1b:1234',
            ipv6Rotator: new Ipv6Rotator(),
            binaryPath: $binaryPath,
        );

        $result = $strategy->execute(
            YouTubeExtractionContext::SUBTITLE,
            'https://youtube.com/watch?v=abc123',
            '/tmp',
            'subs',
            ['--write-auto-sub', '--skip-download'],
        );

        expect($result->isSuccess())->toBeTrue();
        expect($result->stdout)->toContain('--source-address');
        expect($result->stdout)->not->toContain('bestaudio[ext=m4a]/bestaudio');
    } finally {
        unlink($binaryPath);
    }
});
