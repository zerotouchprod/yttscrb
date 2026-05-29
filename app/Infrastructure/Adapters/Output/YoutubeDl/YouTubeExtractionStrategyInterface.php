<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

interface YouTubeExtractionStrategyInterface
{
    /**
     * Human-readable name for logging/metrics (e.g. 'primary', 'cookies', 'ipv6').
     */
    public function name(): string;

    /**
     * Whether this strategy can be used (e.g., cookies file exists, IPv6 prefix configured).
     */
    public function isAvailable(): bool;

    /**
     * Execute the extraction and return a typed result.
     *
     * @param string $url The YouTube URL to extract from.
     * @param string $outputDir Directory where yt-dlp should write files.
     * @param string $outputTemplate yt-dlp output template (e.g. '%(id)s.%(ext)s').
     * @param array<int, string> $extraArgs Additional yt-dlp arguments specific to the caller
     *                                          (e.g. --write-auto-sub, -x --audio-format mp3).
     */
    public function execute(string $url, string $outputDir, string $outputTemplate, array $extraArgs): YouTubeExtractionAttemptResult;
}
