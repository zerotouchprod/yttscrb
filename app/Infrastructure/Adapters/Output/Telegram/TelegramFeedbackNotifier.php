<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Telegram;

use App\Application\DTO\SendFeedbackCommand;
use App\Application\Ports\Output\FeedbackNotifierInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

final class TelegramFeedbackNotifier implements FeedbackNotifierInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(SendFeedbackCommand $command): void
    {
        $token = (string) config('services.telegram.token');
        $chatId = (string) config('services.telegram.chat_id');

        if ($token === '' || $chatId === '') {
            $this->logger->warning('Telegram feedback not configured — skipping notification.');
            return;
        }

        $text = $this->formatMessage($command);

        try {
            $this->http
                ->timeout(3)
                ->asForm()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]);
        } catch (\Throwable $e) {
            $this->logger->error('Telegram feedback notification failed: ' . $e->getMessage());
        }
    }

    private function formatMessage(SendFeedbackCommand $command): string
    {
        $text = '<b>📩 New Feedback from TubeSum</b>' . "\n\n";
        $text .= '<b>Message:</b>' . "\n";
        $text .= htmlspecialchars($command->message, ENT_QUOTES, 'UTF-8');

        if ($command->email !== null) {
            $text .= "\n\n<b>Email:</b> " . htmlspecialchars($command->email, ENT_QUOTES, 'UTF-8');
        }

        return $text;
    }
}
