<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\TranscriptionText;

interface SummaryProviderInterface
{
    public function summarize(TranscriptionText $transcriptText, SummaryOptions $options): SummaryResult;
}
