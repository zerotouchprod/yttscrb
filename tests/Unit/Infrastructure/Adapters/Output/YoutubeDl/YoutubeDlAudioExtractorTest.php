<?php

declare(strict_types=1);

use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeAntiBotExtractionPolicy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionAttemptResult;
use App\Infrastructure\Adapters\Output\YoutubeDl\YoutubeDlAudioExtractor;

test('extract delegates to policy and returns AudioFile on success', function () {
    $policy = Mockery::mock(YouTubeAntiBotExtractionPolicy::class);
    $outputDir = sys_get_temp_dir();

    $videoId = 'dQw4w9WgXcQ';
    $outputFile = $outputDir . '/' . $videoId . '.mp3';

    try {
        $successResult = YouTubeExtractionAttemptResult::success('yt-dlp output', 1000, 'primary');

        $policy->shouldReceive('attempt')
            ->once()
            ->andReturnUsing(function () use ($outputFile, $successResult) {
                // Simulate yt-dlp creating the output file
                file_put_contents($outputFile, 'fake audio data');
                return $successResult;
            });

        $extractor = new YoutubeDlAudioExtractor(
            policy: $policy,
            outputDir: $outputDir,
        );

        $audioFile = $extractor->extract(new YouTubeUrl('https://youtube.com/watch?v=' . $videoId));

        expect($audioFile->path())->toBe($outputFile);
    } finally {
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }
});

test('extract skips download if file already exists', function () {
    $policy = Mockery::mock(YouTubeAntiBotExtractionPolicy::class);
    $outputDir = sys_get_temp_dir();

    $videoId = 'existing123';
    $outputFile = $outputDir . '/' . $videoId . '.mp3';
    file_put_contents($outputFile, 'cached audio');

    try {
        // Policy should NOT be called because file already exists
        $policy->shouldNotReceive('attempt');

        $extractor = new YoutubeDlAudioExtractor(
            policy: $policy,
            outputDir: $outputDir,
        );

        $audioFile = $extractor->extract(new YouTubeUrl('https://youtube.com/watch?v=' . $videoId));

        expect($audioFile->path())->toBe($outputFile);
    } finally {
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }
});

test('extract throws when policy fails and no output file', function () {
    $policy = Mockery::mock(YouTubeAntiBotExtractionPolicy::class);
    $outputDir = sys_get_temp_dir();

    $successResult = YouTubeExtractionAttemptResult::success('yt-dlp output', 1000, 'primary');

    $policy->shouldReceive('attempt')
        ->once()
        ->andReturn($successResult);

    $extractor = new YoutubeDlAudioExtractor(
        policy: $policy,
        outputDir: $outputDir,
    );

    // File was not created by policy — should throw
    expect(fn () => $extractor->extract(new YouTubeUrl('https://youtube.com/watch?v=nonexistent')))
        ->toThrow(RuntimeException::class, 'yt-dlp completed but output file not found');
});
