<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use Illuminate\Container\Container;
use Workflow\Activity;

final class AudioDownloaderActivity extends Activity
{
    public $tries = 3;

    public function execute(string $taskId, string $youtubeUrl): DownloadedAudioResult
    {
        /** @var AudioExtractorInterface $extractor */
        $extractor = Container::getInstance()->make(AudioExtractorInterface::class);

        $audioFile = $extractor->extract(new YouTubeUrl($youtubeUrl));

        return new DownloadedAudioResult($audioFile->path());
    }
}
