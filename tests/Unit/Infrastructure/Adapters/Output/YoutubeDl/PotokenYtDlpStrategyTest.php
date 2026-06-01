<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\PotokenYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionAttemptResult;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionContext;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();
});

test('potoken strategy is unavailable when serviceUrl is null', function () {
    $strategy = new PotokenYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        serviceUrl: null,
    );

    expect($strategy->isAvailable())->toBeFalse();
});

test('potoken strategy is unavailable when sidecar not reachable', function () {
    // 127.0.0.1:4416 is not listening in test environment,
    // so isAvailable() returns false after connectivity check.
    $strategy = new PotokenYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        serviceUrl: 'http://127.0.0.1:4416',
    );

    expect($strategy->isAvailable())->toBeFalse();
    expect($strategy->name())->toBe('potoken');
});

test('potoken strategy command contains po_token extractor arg', function () {
    Http::fake([
        'http://127.0.0.1:4416/token' => Http::response(['token' => 'abc123token'], 200),
    ]);

    $runner = Mockery::mock(YtDlpProcessRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with(Mockery::on(function (string $command): bool {
            return str_contains($command, 'po_token=web+abc123token')
                && str_contains($command, '--extractor-args');
        }))
        ->andReturn(['stdout' => 'output', 'stderr' => '', 'exitCode' => 0]);

    $strategy = new PotokenYtDlpStrategy(
        runner: $runner,
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        serviceUrl: 'http://127.0.0.1:4416',
    );

    $result = $strategy->execute(
        YouTubeExtractionContext::AUDIO,
        'https://youtube.com/watch?v=abc123',
        '/tmp',
        '%(id)s.%(ext)s',
        [],
    );

    expect($result->isSuccess())->toBeTrue();
    expect($result->strategyName)->toBe('potoken');
});

test('potoken token is cached in Redis for 6 hours', function () {
    Http::fake([
        'http://127.0.0.1:4416/token' => Http::response(['token' => 'cachedtoken'], 200),
    ]);

    $runner = Mockery::mock(YtDlpProcessRunner::class);
    $runner->shouldReceive('run')
        ->times(2)
        ->andReturn(['stdout' => 'output', 'stderr' => '', 'exitCode' => 0]);

    $strategy = new PotokenYtDlpStrategy(
        runner: $runner,
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        serviceUrl: 'http://127.0.0.1:4416',
    );

    // First call — should hit HTTP
    $strategy->execute(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc', '/tmp', '%(id)s.%(ext)s', []);

    // Second call — should use cache, NOT hit HTTP again
    $strategy->execute(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=def', '/tmp', '%(id)s.%(ext)s', []);

    // Assert HTTP was called exactly once (second call used cache)
    Http::assertSentCount(1);
});

test('potoken returns retryable failure when service is unreachable', function () {
    Http::fake([
        'http://127.0.0.1:4416/token' => Http::response('', 502),
    ]);

    $strategy = new PotokenYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        serviceUrl: 'http://127.0.0.1:4416',
    );

    $result = $strategy->execute(
        YouTubeExtractionContext::AUDIO,
        'https://youtube.com/watch?v=abc123',
        '/tmp',
        '%(id)s.%(ext)s',
        [],
    );

    expect($result->isRetryable())->toBeTrue();
    expect($result->strategyName)->toBe('potoken');
});

test('potoken returns retryable failure when token response is invalid', function () {
    Http::fake([
        'http://127.0.0.1:4416/token' => Http::response(['not_token' => 'x'], 200),
    ]);

    $strategy = new PotokenYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        serviceUrl: 'http://127.0.0.1:4416',
    );

    $result = $strategy->execute(
        YouTubeExtractionContext::AUDIO,
        'https://youtube.com/watch?v=abc123',
        '/tmp',
        '%(id)s.%(ext)s',
        [],
    );

    expect($result->isRetryable())->toBeTrue();
});
