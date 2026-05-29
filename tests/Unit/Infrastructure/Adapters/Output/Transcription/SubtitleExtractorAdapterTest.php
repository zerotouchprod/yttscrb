<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\Transcription\SrtParser;
use App\Infrastructure\Adapters\Output\Transcription\SubtitleExtractorAdapter;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeAntiBotExtractionPolicy;
use App\Infrastructure\Adapters\Output\YoutubeDl\YouTubeExtractionAttemptResult;
use Tests\TestCase;

uses(TestCase::class);

test('parseDurationOutput returns null for empty array', function () {
    $adapter = new SubtitleExtractorAdapter(
        policy: Mockery::mock(YouTubeAntiBotExtractionPolicy::class),
    );

    expect($adapter->parseDurationOutput([]))->toBeNull();
});

test('parseDurationOutput returns null for empty string', function () {
    $adapter = new SubtitleExtractorAdapter(
        policy: Mockery::mock(YouTubeAntiBotExtractionPolicy::class),
    );

    expect($adapter->parseDurationOutput(['']))->toBeNull();
});

test('parseDurationOutput returns null for non-numeric', function () {
    $adapter = new SubtitleExtractorAdapter(
        policy: Mockery::mock(YouTubeAntiBotExtractionPolicy::class),
    );

    expect($adapter->parseDurationOutput(['not-a-number']))->toBeNull();
});

test('parseDurationOutput parses integer', function () {
    $adapter = new SubtitleExtractorAdapter(
        policy: Mockery::mock(YouTubeAntiBotExtractionPolicy::class),
    );

    expect($adapter->parseDurationOutput(['212']))->toBe(212);
});

test('parseDurationOutput parses float', function () {
    $adapter = new SubtitleExtractorAdapter(
        policy: Mockery::mock(YouTubeAntiBotExtractionPolicy::class),
    );

    expect($adapter->parseDurationOutput(['212.5']))->toBe(212);
});

test('parseDurationOutput takes last non-empty line', function () {
    $adapter = new SubtitleExtractorAdapter(
        policy: Mockery::mock(YouTubeAntiBotExtractionPolicy::class),
    );

    expect($adapter->parseDurationOutput(['warning: something', '212']))->toBe(212);
});

test('extract returns subtitles when policy succeeds and srt file exists', function () {
    $policy = Mockery::mock(YouTubeAntiBotExtractionPolicy::class);
    $srtParser = new SrtParser();

    $outputDir = sys_get_temp_dir() . '/subs-test-' . uniqid();
    mkdir($outputDir, 0755, true);

    // Create a fake SRT file that the adapter would find
    $srtContent = "1\n00:00:01,000 --> 00:00:04,000\nHello world\n";
    file_put_contents($outputDir . '/subs.en.srt', $srtContent);

    // Policy returns success with title and duration in stdout
    $stdout = "Test Video Title\n123.456\n";
    $successResult = YouTubeExtractionAttemptResult::success($stdout, 800, 'primary');

    $policy->shouldReceive('attempt')
        ->once()
        ->with('subtitle', Mockery::type('string'), Mockery::type('string'), 'subs', Mockery::type('array'))
        ->andReturn($successResult);

    $adapter = new SubtitleExtractorAdapter(
        policy: $policy,
        srtParser: $srtParser,
        outputDir: $outputDir,
    );

    try {
        $subtitles = $adapter->extract('https://youtube.com/watch?v=abc123');

        expect($subtitles)->toContain('Hello world');
    } finally {
        array_map('unlink', glob($outputDir . '/*') ?: []);
        rmdir($outputDir);
    }
});

test('extract returns null when policy fails', function () {
    $policy = Mockery::mock(YouTubeAntiBotExtractionPolicy::class);
    $srtParser = new SrtParser();
    $outputDir = sys_get_temp_dir() . '/subs-test-' . uniqid();
    mkdir($outputDir, 0755, true);

    $policy->shouldReceive('attempt')
        ->once()
        ->with('subtitle', Mockery::type('string'), Mockery::type('string'), 'subs', Mockery::type('array'))
        ->andThrow(new RuntimeException('Video unavailable'));

    $adapter = new SubtitleExtractorAdapter(
        policy: $policy,
        srtParser: $srtParser,
        outputDir: $outputDir,
    );

    try {
        $subtitles = $adapter->extract('https://youtube.com/watch?v=abc123');

        expect($subtitles)->toBeNull();
    } finally {
        rmdir($outputDir);
    }
});

test('extractTitle returns title from policy stdout', function () {
    $policy = Mockery::mock(YouTubeAntiBotExtractionPolicy::class);
    $srtParser = new SrtParser();
    $outputDir = sys_get_temp_dir() . '/subs-test-' . uniqid();
    mkdir($outputDir, 0755, true);

    $stdout = "My Amazing Title\n\n\n3600\n";
    $successResult = YouTubeExtractionAttemptResult::success($stdout, 800, 'primary');

    $policy->shouldReceive('attempt')
        ->once()
        ->with('subtitle', Mockery::type('string'), Mockery::type('string'), 'subs', Mockery::type('array'))
        ->andReturn($successResult);

    $adapter = new SubtitleExtractorAdapter(
        policy: $policy,
        srtParser: $srtParser,
        outputDir: $outputDir,
    );

    try {
        $title = $adapter->extractTitle('https://youtube.com/watch?v=abc123');

        expect($title)->toBe('My Amazing Title');
    } finally {
        rmdir($outputDir);
    }
});

test('extractDuration returns duration from policy stdout', function () {
    $policy = Mockery::mock(YouTubeAntiBotExtractionPolicy::class);
    $srtParser = new SrtParser();
    $outputDir = sys_get_temp_dir() . '/subs-test-' . uniqid();
    mkdir($outputDir, 0755, true);

    $stdout = "Title\n3600\n";
    $successResult = YouTubeExtractionAttemptResult::success($stdout, 800, 'primary');

    $policy->shouldReceive('attempt')
        ->once()
        ->with('subtitle', Mockery::type('string'), Mockery::type('string'), 'subs', Mockery::type('array'))
        ->andReturn($successResult);

    $adapter = new SubtitleExtractorAdapter(
        policy: $policy,
        srtParser: $srtParser,
        outputDir: $outputDir,
    );

    try {
        $duration = $adapter->extractDuration('https://youtube.com/watch?v=abc123');

        expect($duration)->toBe(3600);
    } finally {
        rmdir($outputDir);
    }
});
