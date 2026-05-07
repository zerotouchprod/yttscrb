<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class SummaryResult
{
    public function __construct(private string $text)
    {
    }

    public function text(): string
    {
        return $this->text;
    }
}
