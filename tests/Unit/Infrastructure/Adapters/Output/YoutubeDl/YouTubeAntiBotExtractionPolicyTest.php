<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\StrategyCooldownStore;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeAntiBotExtractionPolicy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionAttemptResult;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionContext;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionStrategyInterface;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

uses(TestCase::class);

test('policy returns success from first strategy', function () {
    $successResult = YouTubeExtractionAttemptResult::success('output', 1000, 'primary');

    $strategy = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $strategy->shouldReceive('name')->andReturn('primary');
    $strategy->shouldReceive('isAvailable')->andReturn(true);
    $strategy->shouldReceive('execute')->once()->andReturn($successResult);

    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);

    $store = new StrategyCooldownStore();

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$strategy],
        cooldownStore: $store,
    );

    $result = $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []);

    expect($result->isSuccess())->toBeTrue();
    expect($result->strategyName)->toBe('primary');
});

test('policy switches to next strategy on bot detection', function () {
    $botResult = YouTubeExtractionAttemptResult::botDetected('bot', 500, 'primary');
    $successResult = YouTubeExtractionAttemptResult::success('output', 800, 'cookies');

    $primary = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $primary->shouldReceive('name')->andReturn('primary');
    $primary->shouldReceive('isAvailable')->andReturn(true);
    $primary->shouldReceive('execute')->once()->andReturn($botResult);

    $cookies = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $cookies->shouldReceive('name')->andReturn('cookies');
    $cookies->shouldReceive('isAvailable')->andReturn(true);
    $cookies->shouldReceive('execute')->once()->andReturn($successResult);

    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);
    Redis::shouldReceive('zadd')
        ->once()
        ->withArgs(fn (string $key, int $score, string $member): bool => str_contains($key, 'primary:failure_window') && $score > 0 && $member !== '');
    Redis::shouldReceive('expire')->once();
    Redis::shouldReceive('zcount')->once()->andReturn(1);
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:cookies:cooldown_until')
        ->andReturn(null);

    $store = new StrategyCooldownStore(failureThreshold: 3, cooldownDurationSec: 600, failureWindowSec: 120);

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$primary, $cookies],
        cooldownStore: $store,
    );

    $result = $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []);

    expect($result->isSuccess())->toBeTrue();
    expect($result->strategyName)->toBe('cookies');
});

test('policy retries same strategy on rate limit', function () {
    $rateLimitedResult = YouTubeExtractionAttemptResult::rateLimited('429', 500, 'primary');
    $successResult = YouTubeExtractionAttemptResult::success('output', 800, 'primary');

    $primary = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $primary->shouldReceive('name')->andReturn('primary');
    $primary->shouldReceive('isAvailable')->andReturn(true);
    $primary->shouldReceive('execute')->times(2)->andReturn($rateLimitedResult, $successResult);

    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$primary],
        cooldownStore: new StrategyCooldownStore(),
        maxRetriesPerStrategy: 2,
        retryCooldownSec: 0,
    );

    $result = $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []);

    expect($result->isSuccess())->toBeTrue();
});

test('policy throws on permanent failure', function () {
    $permanentResult = YouTubeExtractionAttemptResult::permanent('Video unavailable', 300, 'primary');

    $primary = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $primary->shouldReceive('name')->andReturn('primary');
    $primary->shouldReceive('isAvailable')->andReturn(true);
    $primary->shouldReceive('execute')->once()->andReturn($permanentResult);

    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$primary],
        cooldownStore: new StrategyCooldownStore(),
    );

    expect(fn () => $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []))
        ->toThrow(\RuntimeException::class, 'Video unavailable');
});

test('policy skips quarantined strategy', function () {
    $successResult = YouTubeExtractionAttemptResult::success('output', 800, 'cookies');

    $primary = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $primary->shouldReceive('name')->andReturn('primary');
    $primary->shouldReceive('isAvailable')->andReturn(true);
    $primary->shouldNotReceive('execute');

    $cookies = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $cookies->shouldReceive('name')->andReturn('cookies');
    $cookies->shouldReceive('isAvailable')->andReturn(true);
    $cookies->shouldReceive('execute')->once()->andReturn($successResult);

    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(time() + 600);
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(time() + 600);
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:cookies:cooldown_until')
        ->andReturn(null);

    $store = new StrategyCooldownStore();

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$primary, $cookies],
        cooldownStore: $store,
    );

    $result = $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []);

    expect($result->isSuccess())->toBeTrue();
    expect($result->strategyName)->toBe('cookies');
});

test('policy throws when all strategies exhausted after last resort', function () {
    // Both first attempt and last resort return bot_detected → should throw
    $botResult = YouTubeExtractionAttemptResult::botDetected('bot', 500, 'primary');

    $primary = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $primary->shouldReceive('name')->andReturn('primary');
    $primary->shouldReceive('isAvailable')->andReturn(true);
    // executeWithRetries does NOT retry bot_detected, so first call = 1 attempt
    // Then last resort calls executeWithRetries again = 1 more attempt
    $primary->shouldReceive('execute')->times(2)->andReturn($botResult, $botResult);

    // First strategy check (not in cooldown)
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);
    // recordFailure after first bot detection
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('expire')->once();
    Redis::shouldReceive('zcount')->once()->andReturn(1);
    // Last resort: check cooldown again before retry
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);
    // recordFailure after last resort bot detection
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('expire')->once();
    Redis::shouldReceive('zcount')->once()->andReturn(2);

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$primary],
        cooldownStore: new StrategyCooldownStore(),
    );

    expect(fn () => $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []))
        ->toThrow(\RuntimeException::class, 'All YouTube extraction strategies exhausted');
});

test('policy succeeds on last resort after bot detected on single strategy', function () {
    $botResult = YouTubeExtractionAttemptResult::botDetected('bot', 500, 'primary');
    $successResult = YouTubeExtractionAttemptResult::success('output', 800, 'primary');

    $primary = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $primary->shouldReceive('name')->andReturn('primary');
    $primary->shouldReceive('isAvailable')->andReturn(true);
    // First call = botDetected, second call (last resort) = success
    $primary->shouldReceive('execute')->times(2)->andReturn($botResult, $successResult);

    // First strategy check (not in cooldown)
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);
    // recordFailure after first bot detection
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('expire')->once();
    Redis::shouldReceive('zcount')->once()->andReturn(1);
    // Last resort: check cooldown again before retry
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$primary],
        cooldownStore: new StrategyCooldownStore(),
    );

    $result = $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []);

    expect($result->isSuccess())->toBeTrue();
    expect($result->strategyName)->toBe('primary');
});

test('policy skips unavailable strategies', function () {
    $successResult = YouTubeExtractionAttemptResult::success('output', 800, 'ipv6');

    $cookies = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $cookies->shouldReceive('name')->andReturn('cookies');
    $cookies->shouldReceive('isAvailable')->andReturn(false);
    $cookies->shouldNotReceive('execute');

    $ipv6 = \Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $ipv6->shouldReceive('name')->andReturn('ipv6');
    $ipv6->shouldReceive('isAvailable')->andReturn(true);
    $ipv6->shouldReceive('execute')->once()->andReturn($successResult);

    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:ipv6:cooldown_until')
        ->andReturn(null);

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$cookies, $ipv6],
        cooldownStore: new StrategyCooldownStore(),
    );

    $result = $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []);

    expect($result->strategyName)->toBe('ipv6');
});

test('policy falls back to proxy strategy after primary bot detected', function () {
    $botResult = YouTubeExtractionAttemptResult::botDetected('bot', 500, 'primary');
    $successResult = YouTubeExtractionAttemptResult::success('output', 800, 'proxy');

    $primary = Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $primary->shouldReceive('name')->andReturn('primary');
    $primary->shouldReceive('isAvailable')->andReturn(true);
    $primary->shouldReceive('execute')->once()->andReturn($botResult);

    $proxy = Mockery::mock(YouTubeExtractionStrategyInterface::class);
    $proxy->shouldReceive('name')->andReturn('proxy');
    $proxy->shouldReceive('isAvailable')->andReturn(true);
    $proxy->shouldReceive('execute')->once()->andReturn($successResult);

    // primary: not in cooldown
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);
    // recordFailure for primary
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('expire')->once();
    Redis::shouldReceive('zcount')->once()->andReturn(1);
    // proxy: not in cooldown
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:proxy:cooldown_until')
        ->andReturn(null);

    $policy = new YouTubeAntiBotExtractionPolicy(
        strategies: [$primary, $proxy],
        cooldownStore: new StrategyCooldownStore(failureThreshold: 3),
    );

    $result = $policy->attempt(YouTubeExtractionContext::AUDIO, 'https://youtube.com/watch?v=abc123', '/tmp', '%(id)s.%(ext)s', []);

    expect($result->isSuccess())->toBeTrue();
    expect($result->strategyName)->toBe('proxy');
});
