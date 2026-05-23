<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\DTO\SendFeedbackCommand;
use App\Application\UseCases\SendFeedbackHandler;
use App\Infrastructure\Adapters\Input\Web\Requests\FeedbackRequest;

final class FeedbackController
{
    public function __construct(
        private readonly SendFeedbackHandler $handler,
    ) {
    }

    public function send(FeedbackRequest $request): \Illuminate\Http\JsonResponse
    {
        $command = new SendFeedbackCommand(
            message: $request->validated('message'),
            email: $request->validated('email'),
        );

        $this->handler->handle($command);

        return response()->json(['message' => 'Thank you for your feedback!']);
    }
}
