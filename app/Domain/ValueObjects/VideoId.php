<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class VideoId
{
    public function __construct(private string $value)
    {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value) !== 1) {
            throw new InvalidArgumentException('YouTube video id must be 11 URL-safe characters.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
