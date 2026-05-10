<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\DTO;

final readonly class DownloadedAudioResult
{
    public function __construct(
        public string $path,
    ) {
    }
}
