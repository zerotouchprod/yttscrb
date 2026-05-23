<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Application\DTO\SendFeedbackCommand;

interface FeedbackNotifierInterface
{
    public function notify(SendFeedbackCommand $command): void;
}
