<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Transcription;

use App\Application\Ports\Output\SubtitleProviderInterface;
use Illuminate\Support\Facades\Log;

final class SubtitleExtractorAdapter implements SubtitleProviderInterface
{
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

        // Convert SRT/VTT to plain text (remove timestamps and sequence numbers)
        return $this->stripTimestamps($content);
    }

    public function extractTitle(string $youtubeUrl): ?string
    {
        $ytDlp = config('services.yt_dlp_binary', 'yt-dlp');

        if (! is_string($ytDlp) || $ytDlp === '') {
            $ytDlp = 'yt-dlp';
        }

        $command = sprintf(
            '%s --print title --skip-download %s 2>&1',
            escapeshellcmd($ytDlp),
            escapeshellarg($youtubeUrl),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        $title = trim(implode("\n", $output));

        return $title !== '' ? $title : null;
    }

    private function stripTimestamps(string $content): string
    {
        $lines = explode("\n", $content);
        $textLines = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if ($line === '') {
                continue;
            }

            // Skip sequence numbers (pure digits)
            if (preg_match('/^\d+$/', $line)) {
                continue;
            }

            // Skip timestamp lines (contain -->)
            if (str_contains($line, '-->')) {
                continue;
            }

            // Skip VTT header
            if ($line === 'WEBVTT' || str_starts_with($line, 'Kind:') || str_starts_with($line, 'Language:')) {
                continue;
            }

            // Remove HTML-like tags
            $line = strip_tags($line);

            if ($line !== '') {
                $textLines[] = $line;
            }
        }

        return implode(' ', $textLines);
    }
}
