<?php

declare(strict_types=1);

namespace App\Application\DTO;

final class SendFeedbackCommand
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $email = null,
    ) {
    }
}
