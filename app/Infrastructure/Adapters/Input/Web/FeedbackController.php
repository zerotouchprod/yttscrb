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
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $command = new SendFeedbackCommand(
            message: (string) ($validated['message'] ?? ''),
            email: isset($validated['email']) && is_string($validated['email']) ? $validated['email'] : null,
        );

        $this->handler->handle($command);

        return response()->json(['message' => 'Thank you for your feedback!']);
    }
}
