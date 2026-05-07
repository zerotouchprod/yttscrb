<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\ValueObjects\AudioFile;
use App\Domain\ValueObjects\YouTubeUrl;

interface AudioExtractorInterface
{
    public function extract(YouTubeUrl $youtubeUrl): AudioFile;
}
