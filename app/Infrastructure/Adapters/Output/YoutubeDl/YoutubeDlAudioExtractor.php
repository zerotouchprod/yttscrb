<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

use App\Application\Ports\Output\AudioExtractorInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Domain\ValueObjects\YouTubeUrl;
use RuntimeException;

final class YoutubeDlAudioExtractor implements AudioExtractorInterface
{
    private const OUTPUT_TEMPLATE = '%(id)s.%(ext)s';

    public function __construct(
        private readonly YouTubeAntiBotExtractionPolicy $policy,
        private readonly string $outputDir = '/tmp',
    ) {
    }

    public function extract(YouTubeUrl $youtubeUrl): AudioFile
    {
        $videoId = $youtubeUrl->videoId()->value();
        $outputPath = $this->outputDir . '/' . $videoId . '.mp3';

        if (file_exists($outputPath)) {
            return new AudioFile($outputPath);
        }

        $extraArgs = [
            '-x',
            '--audio-format',
            'mp3',
        ];

        $this->policy->attempt(
            YouTubeExtractionContext::AUDIO,
            $youtubeUrl->value(),
            $this->outputDir,
            self::OUTPUT_TEMPLATE,
            $extraArgs,
        );

        // Policy succeeded (didn't throw), check for output file
        if (file_exists($outputPath)) {
            return new AudioFile($outputPath);
        }

        throw new RuntimeException(sprintf(
            'yt-dlp completed but output file not found for %s. Expected: %s',
            $youtubeUrl->value(),
            $outputPath,
        ));
    }
}
