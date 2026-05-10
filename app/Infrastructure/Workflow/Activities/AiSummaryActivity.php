<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\SummaryProviderInterface;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\TranscriptionText;
use Illuminate\Container\Container;
use Workflow\Activity;

final class AiSummaryActivity extends Activity
{
    public function execute(string $transcript): string
    {
        /** @var SummaryProviderInterface $provider */
        $provider = Container::getInstance()->make(SummaryProviderInterface::class);

        $result = $provider->summarize(
            new TranscriptionText($transcript),
            new SummaryOptions(),
        );

        return $result->text();
    }
}
