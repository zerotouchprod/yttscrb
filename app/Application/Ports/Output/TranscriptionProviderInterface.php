<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Domain\ValueObjects\AudioFile;
use App\Application\DTO\TranscriptionResult;

interface TranscriptionProviderInterface
{
    public function transcribe(AudioFile $audioFile): TranscriptionResult;
}
