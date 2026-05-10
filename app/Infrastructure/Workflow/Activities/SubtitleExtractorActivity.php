<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\SubtitleProviderInterface;
use Illuminate\Container\Container;
use Workflow\Activity;

final class SubtitleExtractorActivity extends Activity
{
    public function execute(string $youtubeUrl): ?string
    {
        /** @var SubtitleProviderInterface $provider */
        $provider = Container::getInstance()->make(SubtitleProviderInterface::class);

        return $provider->extract($youtubeUrl);
    }
}
