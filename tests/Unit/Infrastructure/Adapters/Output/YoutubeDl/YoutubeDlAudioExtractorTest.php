<?php

declare(strict_types=1);

use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Output\YoutubeDl\YoutubeDlAudioExtractor;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir() . '/yttscrb_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    array_map('unlink', glob($this->tempDir . '/*') ?: []);
    rmdir($this->tempDir);
});

it('returns cached audio file if already exists', function (): void {
    $videoId = 'dQw4w9WgXcQ';
    $existingFile = $this->tempDir . '/' . $videoId . '.mp3';
    touch($existingFile);

    $extractor = new YoutubeDlAudioExtractor('echo', $this->tempDir);
    $result = $extractor->extract(new YouTubeUrl('https://www.youtube.com/watch?v=' . $videoId));

    expect($result->path())->toBe($existingFile);
});

it('throws RuntimeException when yt-dlp binary fails', function (): void {
    $extractor = new YoutubeDlAudioExtractor('false', $this->tempDir);

    $extractor->extract(new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
})->throws(RuntimeException::class, 'yt-dlp failed');

it('throws RuntimeException when output file is not found after successful command', function (): void {
    $extractor = new YoutubeDlAudioExtractor('echo', $this->tempDir);

    $extractor->extract(new YouTubeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
})->throws(RuntimeException::class, 'output file not found');
