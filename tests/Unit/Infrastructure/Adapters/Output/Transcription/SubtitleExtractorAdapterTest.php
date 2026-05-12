<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Adapters\Output\Transcription;

use App\Infrastructure\Adapters\Output\Transcription\SubtitleExtractorAdapter;
use PHPUnit\Framework\TestCase;

final class SubtitleExtractorAdapterTest extends TestCase
{
    public function testParseDurationOutputReturnsNullForEmptyArray(): void
    {
        $adapter = new SubtitleExtractorAdapter();

        $result = $adapter->parseDurationOutput([]);

        $this->assertNull($result);
    }

    public function testParseDurationOutputReturnsNullForEmptyString(): void
    {
        $adapter = new SubtitleExtractorAdapter();

        $result = $adapter->parseDurationOutput(['']);

        $this->assertNull($result);
    }

    public function testParseDurationOutputReturnsNullForNonNumeric(): void
    {
        $adapter = new SubtitleExtractorAdapter();

        $result = $adapter->parseDurationOutput(['not-a-number']);

        $this->assertNull($result);
    }

    public function testParseDurationOutputParsesInteger(): void
    {
        $adapter = new SubtitleExtractorAdapter();

        $result = $adapter->parseDurationOutput(['212']);

        $this->assertSame(212, $result);
    }

    public function testParseDurationOutputParsesFloat(): void
    {
        $adapter = new SubtitleExtractorAdapter();

        $result = $adapter->parseDurationOutput(['212.5']);

        $this->assertSame(212, $result);
    }

    public function testParseDurationOutputTakesLastNonEmptyLine(): void
    {
        $adapter = new SubtitleExtractorAdapter();

        $result = $adapter->parseDurationOutput(['warning: something', '212']);

        $this->assertSame(212, $result);
    }

    public function testExtractDurationReturnsNullWhenYtDlpFails(): void
    {
        $adapter = new SubtitleExtractorAdapter(
            binaryPath: '/usr/bin/false',
        );

        $duration = $adapter->extractDuration('https://youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertNull($duration);
    }
}
