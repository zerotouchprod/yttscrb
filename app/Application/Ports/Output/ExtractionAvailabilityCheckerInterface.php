<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

interface ExtractionAvailabilityCheckerInterface
{
    /**
     * Check whether at least one YouTube extraction strategy is available
     * (not in cooldown/quarantine) and can be used for new extraction attempts.
     *
     * Returns false when ALL strategies are in cooldown — this signals
     * that dispatching a new workflow would immediately fail, so callers
     * should reject the request early instead of flooding the queue.
     */
    public function isAnyStrategyAvailable(): bool;
}
