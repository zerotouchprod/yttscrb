<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Transcription;

use App\Application\DTO\TranscriptionResult;
use App\Application\Ports\Output\TranscriptionProviderInterface;
use App\Domain\ValueObjects\AudioFile;
use App\Domain\ValueObjects\TranscriptionText;
use RuntimeException;

final class GroqWhisperAdapter implements TranscriptionProviderInterface
{
    private const API_URL = 'https://api.groq.com/openai/v1/audio/transcriptions';

    public function transcribe(AudioFile $audioFile): TranscriptionResult
    {
        $apiKey = config('services.groq.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('GROQ_API_KEY is not configured.');
        }

        $ch = curl_init();

        $postFields = [
            'model' => 'whisper-large-v3-turbo',
            'response_format' => 'verbose_json',
            'file' => new \CURLFile($audioFile->path()),
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => self::API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 600, // 10 minutes
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (! is_string($response) || $httpCode !== 200) {
            throw new RuntimeException(
                sprintf('Groq API request failed (HTTP %d): %s', $httpCode, $error ?: $response),
            );
        }

        /** @var mixed $data */
        $data = json_decode($response, true);

        if (! is_array($data) || ! isset($data['text']) || ! is_string($data['text'])) {
            throw new RuntimeException('Groq API returned unexpected response: ' . $response);
        }

        $duration = $data['duration'] ?? 0;

        if (! is_int($duration) && ! is_float($duration)) {
            $duration = 0;
        }

        // Build timecoded transcript from segments when available; fall back to plain text.
        $segments = $data['segments'] ?? [];
        $transcript = is_array($segments) && $segments !== []
            ? $this->formatSegments($segments)
            : $data['text'];

        return new TranscriptionResult(
            new TranscriptionText($transcript),
            (int) round($duration),
        );
    }

    /**
     * Group Groq Whisper segments into ~12-second paragraphs, each prefixed with a timecode.
     *
     * Output format: "[MM:SS] text" or "[HH:MM:SS] text" per paragraph, separated by "\n".
     *
     * @param  array<mixed, mixed> $segments
     */
    private function formatSegments(array $segments): string
    {
        /** @var int PARAGRAPH_INTERVAL_SEC */
        $intervalSec = 12;

        $paragraphs  = [];
        $bucketStart = null;
        /** @var string[] $bucketTexts */
        $bucketTexts = [];

        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }

            $startRaw = $segment['start'] ?? 0;
            $textRaw  = $segment['text']  ?? '';

            if (! is_string($textRaw) && ! is_numeric($textRaw)) {
                continue;
            }

            $text = trim((string) $textRaw);
            if ($text === '') {
                continue;
            }

            $startSec = (int) round(is_numeric($startRaw) ? (float) $startRaw : 0.0);

            if ($bucketStart === null) {
                $bucketStart = $startSec;
            }

            if ($bucketTexts !== [] && ($startSec - $bucketStart) >= $intervalSec) {
                $paragraphs[] = $this->timecodeLabel($bucketStart) . ' ' . implode(' ', $bucketTexts);
                $bucketStart  = $startSec;
                $bucketTexts  = [];
            }

            $bucketTexts[] = $text;
        }

        if ($bucketTexts !== [] && $bucketStart !== null) {
            $paragraphs[] = $this->timecodeLabel($bucketStart) . ' ' . implode(' ', $bucketTexts);
        }

        return implode("\n", $paragraphs);
    }

    /**
     * Format seconds as "[MM:SS]" or "[HH:MM:SS]" for videos ≥ 1 hour.
     */
    private function timecodeLabel(int $seconds): string
    {
        return '[' . gmdate($seconds >= 3600 ? 'H:i:s' : 'i:s', $seconds) . ']';
    }
}
