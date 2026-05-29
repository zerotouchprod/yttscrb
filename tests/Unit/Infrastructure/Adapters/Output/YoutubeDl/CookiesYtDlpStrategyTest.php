<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\CookiesYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionContext;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

uses(TestCase::class);

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

test('cookies strategy does not force audio format for subtitle extraction', function () {
    $cookiesPath = sys_get_temp_dir() . '/cookies-' . uniqid('', true) . '.txt';
    $binaryPath = sys_get_temp_dir() . '/fake-ytdlp-cookies-' . uniqid('', true) . '.sh';

    file_put_contents($cookiesPath, '# Netscape HTTP Cookie File');
    file_put_contents($binaryPath, "#!/bin/sh\nset -eu\nprintf \"%s\\n\" \"$@\"\n");
    chmod($binaryPath, 0755);

    Redis::shouldReceive('get')->once()->with('ytdlp:last-call')->andReturn(null);
    Redis::shouldReceive('set')->once()->with('ytdlp:global-lock', Mockery::type('string'), 'EX', 120, 'NX')->andReturn(true);
    Redis::shouldReceive('set')->once()->with('ytdlp:last-call', Mockery::type('string'));
    Redis::shouldReceive('del')->once()->with('ytdlp:global-lock');

    try {
        $strategy = new CookiesYtDlpStrategy(
            runner: new YtDlpProcessRunner(),
            classifier: new YouTubeExtractionErrorClassifier(),
            rateLimiter: new YtDlpRateLimiter(),
            cookiesPath: $cookiesPath,
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
        expect($result->stdout)->toContain('--cookies');
        expect($result->stdout)->not->toContain('bestaudio[ext=m4a]/bestaudio');
    } finally {
        unlink($cookiesPath);
        unlink($binaryPath);
    }
});
