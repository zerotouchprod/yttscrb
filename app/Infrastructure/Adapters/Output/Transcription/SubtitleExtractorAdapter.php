<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Transcription;

use App\Application\Ports\Output\SubtitleProviderInterface;
use Illuminate\Support\Facades\Log;

final class SubtitleExtractorAdapter implements SubtitleProviderInterface
{
    public function __construct(
        private readonly SrtParser $srtParser = new SrtParser(),
    ) {
    }

    public function extract(string $youtubeUrl): ?string
    {
        $outputDir = storage_path('app/temp/subs');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $ytDlp = config('services.yt_dlp_binary', 'yt-dlp');

        if (! is_string($ytDlp) || $ytDlp === '') {
            $ytDlp = 'yt-dlp';
        }

        // Download auto-generated subtitles without downloading the video
        $command = sprintf(
            '%s --write-auto-sub --skip-download --sub-lang en --convert-subs srt '
            . '--output %s %s 2>&1',
            escapeshellcmd($ytDlp),
            escapeshellarg($outputDir . '/subs'),
            escapeshellarg($youtubeUrl),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::info('yt-dlp subtitle extraction: no subtitles found or command failed', [
                'url' => $youtubeUrl,
                'exitCode' => $exitCode,
            ]);

            return null;
        }

        // Look for the downloaded subtitle file
        $files = glob($outputDir . '/subs*.en.srt') ?: glob($outputDir . '/subs*.en.vtt') ?: [];

        if ($files === []) {
            // Try without language suffix
            $files = glob($outputDir . '/subs*.srt') ?: glob($outputDir . '/subs*.vtt') ?: [];
        }

        if ($files === []) {
            return null;
        }

        $content = file_get_contents($files[0]);

        // Cleanup
        foreach ($files as $file) {
            unlink($file);
        }

        if ($content === false || trim($content) === '') {
            return null;
        }

        // Parse SRT into timecoded transcript: "[MM:SS] text" lines
        return $this->srtParser->parse($content);
    }

    public function extractTitle(string $youtubeUrl): ?string
    {
        $ytDlp = config('services.yt_dlp_binary', 'yt-dlp');

        if (! is_string($ytDlp) || $ytDlp === '') {
            $ytDlp = 'yt-dlp';
        }

        // --print title writes to stdout; stderr (warnings) is discarded
        $command = sprintf(
            '%s --print title --skip-download %s 2>/dev/null',
            escapeshellcmd($ytDlp),
            escapeshellarg($youtubeUrl),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        // Take only the last non-empty line — the actual title
        $title = '';
        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim($output[$i]);
            if ($line !== '') {
                $title = $line;
                break;
            }
        }

        if ($title === '') {
            return null;
        }

        // Truncate to 500 chars max (safe for DB column)
        if (mb_strlen($title) > 500) {
            $title = mb_substr($title, 0, 497) . '...';
        }

        return $title;
    }
}
