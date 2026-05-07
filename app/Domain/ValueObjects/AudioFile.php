<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class AudioFile
{
    public function __construct(private string $path)
    {
        if ($path === '') {
            throw new InvalidArgumentException('Audio file path must not be empty.');
        }
    }

    public function path(): string
    {
        return $this->path;
    }
}
