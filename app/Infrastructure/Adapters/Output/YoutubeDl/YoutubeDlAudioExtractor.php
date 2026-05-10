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
        private readonly string $binaryPath = 'yt-dlp',
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

        $command = sprintf(
            '%s -x --audio-format mp3 -o %s --no-playlist --print filename %s 2>&1',
            escapeshellcmd($this->binaryPath),
            escapeshellarg($this->outputDir . '/' . self::OUTPUT_TEMPLATE),
            escapeshellarg($youtubeUrl->value()),
        );

        $output = $this->executeCommand($command);

        $actualPath = trim($output);

        if ($actualPath !== '' && file_exists($actualPath)) {
            return new AudioFile($actualPath);
        }

        if (file_exists($outputPath)) {
            return new AudioFile($outputPath);
        }

        throw new RuntimeException(sprintf(
            'yt-dlp completed but output file not found for %s. Expected: %s',
            $youtubeUrl->value(),
            $outputPath,
        ));
    }

    private function executeCommand(string $command): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start yt-dlp process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'yt-dlp failed with exit code %d: %s',
                $exitCode,
                $stderr ?: $stdout,
            ));
        }

        return $stdout;
    }
}
