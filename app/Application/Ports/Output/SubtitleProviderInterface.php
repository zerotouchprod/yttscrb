<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

interface SubtitleProviderInterface
{
    public function extract(string $youtubeUrl): ?string;
}
