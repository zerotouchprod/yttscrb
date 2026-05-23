<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Thrown when yt-dlp cannot download a video for reasons that are NOT bugs:
 * geo-block, copyright claim, private video, region-restricted, etc.
 *
 * These are user-facing errors — the task should fail gracefully without
 * a Sentry alert. Sentry is configured to ignore this exception class.
 */
final class VideoNotAvailableException extends RuntimeException
{
}
