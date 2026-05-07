<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\ValueObjects\TranscriptionText;

final readonly class TranscriptionResult
{
    public function __construct(
        private TranscriptionText $text,
        private int $durationSec,
    ) {
    }

    public function text(): TranscriptionText
    {
        return $this->text;
    }

    public function durationSec(): int
    {
        return $this->durationSec;
    }
}
