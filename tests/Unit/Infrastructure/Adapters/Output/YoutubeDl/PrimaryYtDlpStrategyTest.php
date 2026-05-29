<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\PrimaryYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionContext;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

uses(TestCase::class);

function makeFakeYtDlpBinary(string $body): string
{
    $path = sys_get_temp_dir() . '/fake-ytdlp-' . uniqid('', true) . '.sh';
    file_put_contents($path, "#!/bin/sh\nset -eu\n{$body}\n");
    chmod($path, 0755);

    return $path;
}

function expectRateLimiterAcquireAndRelease(): void
{
    Redis::shouldReceive('get')->once()->with('ytdlp:last-call')->andReturn(null);
    Redis::shouldReceive('set')
        ->once()
        ->with('ytdlp:global-lock', Mockery::type('string'), 'EX', 120, 'NX')
        ->andReturn(true);
    Redis::shouldReceive('set')->once()->with('ytdlp:last-call', Mockery::type('string'));
    Redis::shouldReceive('del')->once()->with('ytdlp:global-lock');
}

test('primary strategy is always available', function () {
    $strategy = new PrimaryYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
    );

    expect($strategy->isAvailable())->toBeTrue();
    expect($strategy->name())->toBe('primary');
});

test('primary strategy builds command with player_client args', function () {
    expectRateLimiterAcquireAndRelease();

    $binaryPath = makeFakeYtDlpBinary('printf "%s\n" "$@"');

    try {
        $strategy = new PrimaryYtDlpStrategy(
            runner: new YtDlpProcessRunner(),
            classifier: new YouTubeExtractionErrorClassifier(),
            rateLimiter: new YtDlpRateLimiter(),
            binaryPath: $binaryPath,
        );

        $result = $strategy->execute(
            YouTubeExtractionContext::AUDIO,
            'https://youtube.com/watch?v=abc123',
            '/tmp',
            '%(id)s.%(ext)s',
            ['-x', '--audio-format', 'mp3'],
        );

        expect($result->isSuccess())->toBeTrue();
        expect($result->stdout)->toContain('youtube:player_client=android,ios,web');
        expect($result->stdout)->toContain('bestaudio[ext=m4a]/bestaudio');
        expect($result->stdout)->toContain('--audio-format');
    } finally {
        unlink($binaryPath);
    }
});

test('primary strategy does not force audio format for subtitle extraction', function () {
    expectRateLimiterAcquireAndRelease();

    $binaryPath = makeFakeYtDlpBinary('printf "%s\n" "$@"');

    try {
        $strategy = new PrimaryYtDlpStrategy(
            runner: new YtDlpProcessRunner(),
            classifier: new YouTubeExtractionErrorClassifier(),
            rateLimiter: new YtDlpRateLimiter(),
            binaryPath: $binaryPath,
        );

        $result = $strategy->execute(
            YouTubeExtractionContext::SUBTITLE,
            'https://youtube.com/watch?v=abc123',
            '/tmp',
            'subs',
            ['--write-auto-sub', '--skip-download', '--print', 'title'],
        );

        expect($result->isSuccess())->toBeTrue();
        expect($result->stdout)->not->toContain('bestaudio[ext=m4a]/bestaudio');
        expect($result->stdout)->toContain('--write-auto-sub');
    } finally {
        unlink($binaryPath);
    }
});

test('primary strategy keeps stderr out of stdout for successful runs', function () {
    expectRateLimiterAcquireAndRelease();

    $binaryPath = makeFakeYtDlpBinary('echo "WARNING: noisy warning" >&2; printf "Title\\n123\\n"');

    try {
        $strategy = new PrimaryYtDlpStrategy(
            runner: new YtDlpProcessRunner(),
            classifier: new YouTubeExtractionErrorClassifier(),
            rateLimiter: new YtDlpRateLimiter(),
            binaryPath: $binaryPath,
        );

        $result = $strategy->execute(
            YouTubeExtractionContext::SUBTITLE,
            'https://youtube.com/watch?v=abc123',
            '/tmp',
            'subs',
            ['--write-auto-sub', '--skip-download', '--print', 'title', '--print', 'duration'],
        );

        expect($result->isSuccess())->toBeTrue();
        expect($result->stdout)->toBe("Title\n123\n");
    } finally {
        unlink($binaryPath);
    }
});
