<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class SummaryOptions
{
    public function __construct(
        private string $style = 'concise',
        private int $maxWords = 250,
    ) {
    }

    public function style(): string
    {
        return $this->style;
    }

    public function maxWords(): int
    {
        return $this->maxWords;
    }
}
