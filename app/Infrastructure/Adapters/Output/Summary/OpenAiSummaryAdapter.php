<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Summary;

use App\Application\DTO\SummaryResult;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\TranscriptionText;
use RuntimeException;

final class OpenAiSummaryAdapter implements SummaryProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function summarize(TranscriptionText $transcriptText, SummaryOptions $options): SummaryResult
    {
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $prompt = sprintf(
            "Summarize the following transcript in %s style in no more than %d words. "
            . "Focus on key points and main ideas.\n\n%s",
            $options->style(),
            $options->maxWords(),
            $transcriptText->value(),
        );

        $ch = curl_init();

        $payload = json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options->maxWords() * 2,
            'temperature' => 0.3,
        ]);

        if (! is_string($payload)) {
            throw new RuntimeException('Failed to encode OpenAI request payload.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => self::API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (! is_string($response) || $httpCode !== 200) {
            throw new RuntimeException(
                sprintf('OpenAI API request failed (HTTP %d): %s', $httpCode, $error ?: $response),
            );
        }

        /** @var array{choices: array{0: array{message: array{content: string}}}|null}|null $data */
        $data = json_decode($response, true);

        if (! is_array($data) || ! isset($data['choices'][0]['message']['content'])) {
            throw new RuntimeException('OpenAI API returned unexpected response: ' . $response);
        }

        return new SummaryResult(trim($data['choices'][0]['message']['content']));
    }
}
