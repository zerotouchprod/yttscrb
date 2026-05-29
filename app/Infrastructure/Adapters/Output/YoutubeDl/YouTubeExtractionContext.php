<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

final class YouTubeExtractionContext
{
    public const AUDIO = 'audio';
    public const SUBTITLE = 'subtitle';

    public static function isAudio(string $context): bool
    {
        return $context === self::AUDIO;
    }
}
