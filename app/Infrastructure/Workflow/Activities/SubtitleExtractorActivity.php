<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use App\Application\Ports\Output\SubtitleProviderInterface;
use Illuminate\Container\Container;
use Workflow\Activity;

final class SubtitleExtractorActivity extends Activity
{
    /** @var int */
    public $tries = 3;

    /**
     * @return array{subtitles: string|null, title: string|null, duration_sec: int|null}
     */
    public function execute(string $youtubeUrl): array
    {
        /** @var SubtitleProviderInterface $provider */
        $provider = Container::getInstance()->make(SubtitleProviderInterface::class);

        try {
            return [
                'subtitles' => $provider->extract($youtubeUrl),
                'title' => $provider->extractTitle($youtubeUrl),
                'duration_sec' => $provider->extractDuration($youtubeUrl),
            ];
        } catch (\Throwable $e) {
            // Subtitle extraction is best-effort. If the provider crashes
            // (e.g. stream_select interrupted by signal), fall through to
            // the audio extraction path instead of failing the workflow.
            return [
                'subtitles' => null,
                'title' => null,
                'duration_sec' => null,
            ];
        }
    }
}
