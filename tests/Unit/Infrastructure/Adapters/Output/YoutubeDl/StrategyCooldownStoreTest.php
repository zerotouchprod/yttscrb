<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\StrategyCooldownStore;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

uses(TestCase::class);

test('strategy is not in cooldown initially', function () {
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(null);

    $store = new StrategyCooldownStore();
    expect($store->isInCooldown('primary'))->toBeFalse();
});

test('record failure puts strategy in cooldown after threshold', function () {
    Redis::shouldReceive('zadd')->times(3);
    Redis::shouldReceive('expire')->times(4);
    Redis::shouldReceive('zcount')->times(3)->andReturn(1, 2, 3);
    Redis::shouldReceive('set')->once();
    Redis::shouldReceive('del')->once()->with('youtube-extractor:strategy:primary:failure_window');
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(time() + 600);

    $store = new StrategyCooldownStore(
        failureThreshold: 3,
        cooldownDurationSec: 600,
        failureWindowSec: 60,
    );

    $store->recordFailure('primary');
    $store->recordFailure('primary');
    $store->recordFailure('primary');
    expect($store->isInCooldown('primary'))->toBeTrue();
});

test('cooldown expires after duration', function () {
    Redis::shouldReceive('zadd')->times(2);
    Redis::shouldReceive('expire')->times(3);
    Redis::shouldReceive('zcount')->times(2)->andReturn(1, 2);
    Redis::shouldReceive('set')->once();
    Redis::shouldReceive('del')->once()->with('youtube-extractor:strategy:primary:failure_window');
    Redis::shouldReceive('get')
        ->twice()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(time() + 2, time() - 1);

    $store = new StrategyCooldownStore(
        failureThreshold: 2,
        cooldownDurationSec: 2,
        failureWindowSec: 60,
    );

    $store->recordFailure('primary');
    $store->recordFailure('primary');
    expect($store->isInCooldown('primary'))->toBeTrue();

    expect($store->isInCooldown('primary'))->toBeFalse();
});

test('reset clears cooldown state', function () {
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('expire')->times(2);
    Redis::shouldReceive('zcount')->once()->andReturn(1);
    Redis::shouldReceive('set')->once();
    Redis::shouldReceive('del')->once()->with('youtube-extractor:strategy:primary:failure_window');
    Redis::shouldReceive('get')
        ->twice()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(time() + 600, null);
    Redis::shouldReceive('del')->once()->with('youtube-extractor:strategy:primary:cooldown_until');
    Redis::shouldReceive('del')->once()->with('youtube-extractor:strategy:primary:failure_window');

    $store = new StrategyCooldownStore(failureThreshold: 1, cooldownDurationSec: 600, failureWindowSec: 60);

    $store->recordFailure('primary');
    expect($store->isInCooldown('primary'))->toBeTrue();

    $store->reset('primary');
    expect($store->isInCooldown('primary'))->toBeFalse();
});

test('get cooldown remaining seconds', function () {
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('expire')->times(2);
    Redis::shouldReceive('zcount')->once()->andReturn(1);
    Redis::shouldReceive('set')->once();
    Redis::shouldReceive('del')->once()->with('youtube-extractor:strategy:primary:failure_window');
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(time() + 600);

    $store = new StrategyCooldownStore(failureThreshold: 1, cooldownDurationSec: 600, failureWindowSec: 60);

    $store->recordFailure('primary');
    $remaining = $store->getCooldownRemainingSec('primary');

    expect($remaining)->toBeGreaterThan(0);
    expect($remaining)->toBeLessThanOrEqual(600);
});

test('different strategies have independent cooldowns', function () {
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('expire')->times(2);
    Redis::shouldReceive('zcount')->once()->andReturn(1);
    Redis::shouldReceive('set')->once();
    Redis::shouldReceive('del')->once()->with('youtube-extractor:strategy:primary:failure_window');
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:primary:cooldown_until')
        ->andReturn(time() + 600);
    Redis::shouldReceive('get')
        ->once()
        ->with('youtube-extractor:strategy:cookies:cooldown_until')
        ->andReturn(null);

    $store = new StrategyCooldownStore(failureThreshold: 1, cooldownDurationSec: 600, failureWindowSec: 60);

    $store->recordFailure('primary');
    expect($store->isInCooldown('primary'))->toBeTrue();
    expect($store->isInCooldown('cookies'))->toBeFalse();
});
