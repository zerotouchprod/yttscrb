<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Redis\Factory as RedisFactory;

/**
 * @internal
 */
final class NullRedisFactory implements RedisFactory
{
    /** @return never */
    public function connection(mixed $name = null)
    {
        throw new \LogicException('NullRedisFactory must not be called; override all methods in FakeRedisViewTracker.');
    }
}
