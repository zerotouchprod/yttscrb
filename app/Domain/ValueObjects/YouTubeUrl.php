<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class YouTubeUrl
{
    private VideoId $videoId;

    public function __construct(private string $value)
    {
        if (preg_match('#^(https?://)?(www\.|m\.)?(youtube\.com|youtu\.be)/.+$#', $value) !== 1) {
            throw new InvalidArgumentException('The provided URL is not a supported YouTube URL.');
        }

        $this->videoId = new VideoId($this->extractVideoId($value));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function videoId(): VideoId
    {
        return $this->videoId;
    }

    private function extractVideoId(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if (str_ends_with($host, 'youtu.be')) {
            return trim($parts['path'] ?? '', '/');
        }

        parse_str($parts['query'] ?? '', $query);
        $videoId = $query['v'] ?? null;

        if (! is_string($videoId)) {
            throw new InvalidArgumentException('The provided YouTube URL does not contain a video id.');
        }

        return $videoId;
    }
}
