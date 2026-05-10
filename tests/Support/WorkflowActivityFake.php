<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A fake activity handler that records calls and returns predefined values.
 *
 * This simulates the durable-workflow engine's activity dispatch without
 * requiring the actual workflow runtime.
 */
final class WorkflowActivityFake
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array{activity: string, args: list<mixed>}> */
    public array $callsWithArgs = [];

    /**
     * @param array<string, mixed> $returns
     */
    public function __construct(
        private readonly array $returns = [],
    ) {
    }

    /**
     * Simulate yielding an activity: record the call and return the predefined value.
     *
     * @param array{activity: string, args: list<mixed>} $yield
     */
    public function dispatch(array $yield): mixed
    {
        $this->calls[] = $yield['activity'];
        $this->callsWithArgs[] = $yield;

        $activity = $yield['activity'];

        if (array_key_exists($activity, $this->returns)) {
            return $this->returns[$activity];
        }

        return null;
    }
}
