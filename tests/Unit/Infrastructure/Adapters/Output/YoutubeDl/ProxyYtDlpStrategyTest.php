<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\ProxyYtDlpStrategy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionAttemptResult;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionContext;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionErrorClassifier;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;
use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpRateLimiter;
use Tests\TestCase;

uses(TestCase::class);

test('proxy strategy is unavailable when proxyUrl is null', function () {
    $strategy = new ProxyYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        proxyUrl: null,
    );

    expect($strategy->isAvailable())->toBeFalse();
});

test('proxy strategy is unavailable when proxyUrl is empty', function () {
    $strategy = new ProxyYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        proxyUrl: '',
    );

    expect($strategy->isAvailable())->toBeFalse();
});

test('proxy strategy is available when proxyUrl is set', function () {
    $strategy = new ProxyYtDlpStrategy(
        runner: new YtDlpProcessRunner(),
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        proxyUrl: 'http://proxy.example.com:8080',
    );

    expect($strategy->isAvailable())->toBeTrue();
    expect($strategy->name())->toBe('proxy');
});

test('proxy strategy includes --proxy flag in command', function () {
    $successResult = YouTubeExtractionAttemptResult::success('output', 1000, 'proxy');

    $runner = Mockery::mock(YtDlpProcessRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with(Mockery::on(function (string $command): bool {
            return str_contains($command, '--proxy ')
                && str_contains($command, 'http://proxy.example.com:8080')
                && str_contains($command, '--extractor-args');
        }))
        ->andReturn(['stdout' => 'output', 'stderr' => '', 'exitCode' => 0]);

    $strategy = new ProxyYtDlpStrategy(
        runner: $runner,
        classifier: new YouTubeExtractionErrorClassifier(),
        rateLimiter: new YtDlpRateLimiter(),
        proxyUrl: 'http://proxy.example.com:8080',
    );

    $result = $strategy->execute(
        YouTubeExtractionContext::SUBTITLE,
        'https://youtube.com/watch?v=abc123',
        '/tmp',
        '%(id)s.%(ext)s',
        [],
    );

    expect($result->isSuccess())->toBeTrue();
    expect($result->strategyName)->toBe('proxy');
});
