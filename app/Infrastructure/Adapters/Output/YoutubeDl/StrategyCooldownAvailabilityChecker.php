<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

use App\Application\Ports\Output\ExtractionAvailabilityCheckerInterface;

final readonly class StrategyCooldownAvailabilityChecker implements ExtractionAvailabilityCheckerInterface
{
    public function __construct(
        private YouTubeAntiBotExtractionPolicy $policy,
    ) {
    }

    public function isAnyStrategyAvailable(): bool
    {
        return $this->policy->hasAvailableStrategy();
    }
}
