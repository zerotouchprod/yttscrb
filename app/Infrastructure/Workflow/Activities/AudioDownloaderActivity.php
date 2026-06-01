<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Workflow\DTO\DownloadedAudioResult;
use Illuminate\Container\Container;
use RuntimeException;
use Workflow\Activity;
use Workflow\Exceptions\NonRetryableException;

final class AudioDownloaderActivity extends Activity
{
    /** @var int */
    public $tries = 3;

    /** @var int Seconds between retries */
    public int $retryDelay = 30;

    public function execute(string $taskId, string $youtubeUrl): DownloadedAudioResult
    {
        /** @var AudioExtractorInterface $extractor */
        $extractor = Container::getInstance()->make(AudioExtractorInterface::class);

        try {
            $audioFile = $extractor->extract(new YouTubeUrl($youtubeUrl));

            return new DownloadedAudioResult($audioFile->path());
        } catch (RuntimeException $e) {
            // Bot detection / all strategies exhausted — non-retryable.
            // Retrying the same strategy won't help; fail fast so the workflow
            // can call failTask and the worker is not blocked.
            if (str_contains($e->getMessage(), 'exhausted or quarantined')) {
                throw new NonRetryableException(
                    $e->getMessage(),
                    (int) $e->getCode(),
                    $e,
                );
            }

            throw $e;
        }
    }
}
