<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;
use Workflow\Exceptions\NonRetryableExceptionContract;

/**
 * Thrown when a transcription permanently fails — the workflow should NOT retry.
 *
 * Examples: file too large, file not found, members-only video, private video.
 */
final class PermanentTranscriptionException extends RuntimeException implements NonRetryableExceptionContract
{
}
