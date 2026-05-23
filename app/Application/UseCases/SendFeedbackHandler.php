<?php

declare(strict_types=1);

namespace App\Application\UseCases;

use App\Application\DTO\SendFeedbackCommand;
use App\Application\Ports\Output\FeedbackNotifierInterface;

final class SendFeedbackHandler
{
    public function __construct(
        private readonly FeedbackNotifierInterface $notifier,
    ) {
    }

    public function handle(SendFeedbackCommand $command): void
    {
        $this->notifier->notify($command);
    }
}
