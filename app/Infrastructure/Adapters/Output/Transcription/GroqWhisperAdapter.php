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

    /** @var int Maximum file size in bytes (25 MB — Groq hard limit) */
    private const MAX_FILE_SIZE = 25 * 1024 * 1024;

    /** @var int Compress any file above this threshold (5 MB) to speed up upload */
    private const COMPRESS_THRESHOLD = 5 * 1024 * 1024;

    /** @var int Maximum number of retries for transient failures */
    private const MAX_RETRIES = 3;

    /** @var int Base delay in seconds between retries (exponential backoff) */
    private const RETRY_BASE_DELAY_SEC = 5;

    public function transcribe(AudioFile $audioFile): TranscriptionResult
    {
        $apiKey = config('services.groq.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('GROQ_API_KEY is not configured.');
        }

        $audioPath = $audioFile->path();
        $fileSize = @filesize($audioPath);

        if ($fileSize !== false && $fileSize > self::MAX_FILE_SIZE) {
            throw new RuntimeException(
                sprintf(
                    'Audio file too large for Groq API (%d MB, max %d MB). Try a shorter video.',
                    (int) round($fileSize / 1024 / 1024),
                    (int) (self::MAX_FILE_SIZE / 1024 / 1024),
                ),
            );
        }

        // Compress any audio > 5 MB to speed up upload and reduce memory pressure.
        if ($fileSize !== false && $fileSize > self::COMPRESS_THRESHOLD) {
            $audioPath = $this->compressAudio($audioPath);
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return $this->doRequest($apiKey, $audioPath);
            } catch (RuntimeException $e) {
                $lastException = $e;

                // Do not retry on client errors (4xx) or permanent failures.
                if ($this->isNonRetryable($e)) {
                    throw $e;
                }

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_BASE_DELAY_SEC * (2 ** ($attempt - 1));
                    sleep($delay);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Execute a single Groq API transcription request.
     */
    private function doRequest(string $apiKey, string $audioPath): TranscriptionResult
    {
        if (! file_exists($audioPath) || ! is_readable($audioPath)) {
            throw new RuntimeException(
                sprintf(
                    'Audio file not found or not readable: %s (exists=%s, size=%s)',
                    $audioPath,
                    file_exists($audioPath) ? 'yes' : 'no',
                    file_exists($audioPath) ? (string) filesize($audioPath) : 'N/A',
                ),
            );
        }

        $ch = curl_init();

        $postFields = [
            'model' => 'whisper-large-v3-turbo',
            'response_format' => 'verbose_json',
            'file' => new \CURLFile($audioPath),
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => self::API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 900, // 15 minutes — large files need time to upload + transcribe
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (! is_string($response) || $httpCode !== 200) {
            throw new RuntimeException(
                sprintf(
                    'Groq API request failed (HTTP %d, errno %d): %s',
                    $httpCode,
                    $curlErrno,
                    $error ?: $response,
                ),
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
     * Determine whether an exception is non-retryable (client error or permanent failure).
     */
    private function isNonRetryable(RuntimeException $e): bool
    {
        $message = $e->getMessage();

        // HTTP 4xx errors are client errors — do not retry.
        if (preg_match('/HTTP (\d+)/', $message, $matches) === 1) {
            $statusCode = (int) $matches[1];

            return $statusCode >= 400 && $statusCode < 500;
        }

        // Permanent failures: invalid API key, file too large, compression failure.
        $nonRetryablePatterns = [
            'not configured',
            'too large',
            'compress',
            'unexpected response',
        ];

        foreach ($nonRetryablePatterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compress audio file to mono 8kbps MP3 (16kHz) for faster upload.
     *
     * Returns path to compressed file (temporary, cleaned up after transcription).
     */
    private function compressAudio(string $sourcePath): string
    {
        $destPath = $sourcePath . '.compressed.mp3';

        $command = sprintf(
            'ffmpeg -y -i %s -ac 1 -ar 16000 -b:a 8k -f mp3 %s 2>/dev/null',
            escapeshellarg($sourcePath),
            escapeshellarg($destPath),
        );

        exec($command, result_code: $exitCode);

        if (! file_exists($destPath) || filesize($destPath) === 0) {
            $originalSize = filesize($sourcePath) ?: 0;
            throw new RuntimeException(
                sprintf(
                    'Failed to compress audio (exit code: %d, original: %d bytes).',
                    $exitCode,
                    $originalSize,
                ),
            );
        }

        return $destPath;
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
