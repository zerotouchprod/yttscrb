<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

enum TranscriptionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => $target === self::Processing,
            self::Processing => $target === self::Completed || $target === self::Failed,
            self::Completed, self::Failed => false,
        };
    }
}
