<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Transcription;

/**
 * Parses SRT subtitles (including YouTube auto-generated) into a timecoded plain-text transcript.
 *
 * Algorithm:
 *  1. Parse every cue into {startSec, text} structs.
 *  2. Merge YouTube roll-up: consecutive cues whose start times fall within a 3-second window
 *     are collapsed into a single cue (we keep the window's opening timestamp but replace the
 *     text with the last — most complete — form of the phrase).
 *  3. Group merged cues into ~12-second paragraphs to keep token count reasonable.
 *  4. Format each paragraph as "[MM:SS] text" or "[HH:MM:SS] text" for videos ≥ 1 hour.
 */
final class SrtParser
{
    /** Max gap (seconds) between consecutive cues treated as the same roll-up utterance. */
    private const int ROLLUP_WINDOW_SEC = 3;

    /** Target paragraph length in seconds (one timecode label per bucket). */
    private const int PARAGRAPH_INTERVAL_SEC = 12;

    /**
     * Parse SRT content and return a timecoded transcript string.
     *
     * Each output line: "[MM:SS] text" or "[HH:MM:SS] text".
     * Lines are separated by "\n".
     */
    public function parse(string $content): string
    {
        $cues    = $this->parseCues($content);
        $merged  = $this->mergeRollup($cues);
        return $this->formatParagraphs($merged);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Parse raw SRT content into a flat list of cues.
     *
     * @return array<int, array{startSec: int, text: string}>
     */
    private function parseCues(string $content): array
    {
        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);
        $lines   = explode("\n", $content);

        $cues      = [];
        $startSec  = null;
        $textLines = [];
        $inCue     = false;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            // Skip VTT / SRT header markers
            if (
                $line === 'WEBVTT'
                || str_starts_with($line, 'Kind:')
                || str_starts_with($line, 'Language:')
                || str_starts_with($line, 'NOTE ')
            ) {
                continue;
            }

            // Empty line → end of current cue block
            if ($line === '') {
                if ($startSec !== null && $textLines !== []) {
                    // implode of non-empty string[] is always non-empty (PHPStan confirms)
                    $cues[] = ['startSec' => $startSec, 'text' => implode(' ', $textLines)];
                }
                $startSec  = null;
                $textLines = [];
                $inCue     = false;
                continue;
            }

            // Sequence number (pure digits before the timestamp) → skip
            if (preg_match('/^\d+$/', $line) && ! $inCue) {
                continue;
            }

            // Timestamp line: HH:MM:SS,mmm --> HH:MM:SS,mmm (SRT)
            //              or HH:MM:SS.mmm --> HH:MM:SS.mmm (VTT)
            if (
                $startSec === null &&
                preg_match(
                    '/^(\d{1,2}):(\d{2}):(\d{2})[,.]\d+\s*-->/',
                    $line,
                    $m,
                )
            ) {
                $startSec = (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
                $inCue = true;
                continue;
            }

            // Text line inside a cue block
            if ($inCue) {
                $cleaned = strip_tags($line);
                // strip_tags MAY produce '' even from non-empty input (e.g. "<b></b>")
                if ($cleaned !== '') {
                    $textLines[] = $cleaned;
                }
            }
        }

        // Flush the last cue (file may not end with a blank line)
        if ($startSec !== null && $textLines !== []) {
            $cues[] = ['startSec' => $startSec, 'text' => implode(' ', $textLines)];
        }

        return $cues;
    }

    /**
     * Merge YouTube roll-up subtitle groups.
     *
     * YouTube auto-subtitles emit each phrase several times, each time with one or more
     * additional / corrected words:
     *   [00:01] я пакажу вам
     *   [00:01] я покажу вам как
     *   [00:03] я покажу вам как играть
     *
     * Strategy: maintain a sliding window anchored at the *first* cue in an utterance.
     * Any cue whose start time falls within ROLLUP_WINDOW_SEC of the window anchor is
     * considered a roll-up expansion — we replace the pending text with the latest version
     * (the most corrected/complete form) while keeping the anchor timestamp.
     *
     * A cue that starts more than ROLLUP_WINDOW_SEC after the window anchor opens a new
     * utterance window.
     *
     * @param  array<int, array{startSec: int, text: string}> $cues
     * @return array<int, array{startSec: int, text: string}>
     */
    private function mergeRollup(array $cues): array
    {
        if ($cues === []) {
            return [];
        }

        $merged      = [];
        $windowStart = $cues[0]['startSec'];
        $pending     = $cues[0];

        for ($i = 1, $count = count($cues); $i < $count; $i++) {
            $cur = $cues[$i];

            if (($cur['startSec'] - $windowStart) <= self::ROLLUP_WINDOW_SEC) {
                // Roll-up continuation: keep window anchor, take new (more complete) text
                $pending = ['startSec' => $windowStart, 'text' => $cur['text']];
            } else {
                // New utterance
                $merged[]    = $pending;
                $windowStart = $cur['startSec'];
                $pending     = $cur;
            }
        }

        $merged[] = $pending;

        return $merged;
    }

    /**
     * Group roll-up-merged cues into ~PARAGRAPH_INTERVAL_SEC buckets.
     * Each bucket becomes one "[timecode] text" output line.
     *
     * @param array<int, array{startSec: int, text: string}> $cues
     */
    private function formatParagraphs(array $cues): string
    {
        if ($cues === []) {
            return '';
        }

        $paragraphs  = [];
        $bucketStart = $cues[0]['startSec'];
        $bucketTexts = [];

        foreach ($cues as $cue) {
            if ($bucketTexts !== [] && ($cue['startSec'] - $bucketStart) >= self::PARAGRAPH_INTERVAL_SEC) {
                // Emit current bucket
                $paragraphs[] = $this->timecodeLabel($bucketStart) . ' ' . implode(' ', $bucketTexts);
                $bucketStart  = $cue['startSec'];
                $bucketTexts  = [];
            }
            $bucketTexts[] = $cue['text'];
        }

        // $bucketTexts is always non-empty here: $cues is non-empty (guarded above) so the
        // loop executed at least once and $bucketTexts[] = $cue['text'] ran at least once.
        $paragraphs[] = $this->timecodeLabel($bucketStart) . ' ' . implode(' ', $bucketTexts);

        return implode("\n", $paragraphs);
    }

    /**
     * Format seconds as "[MM:SS]" for videos < 1 h, or "[HH:MM:SS]" for videos ≥ 1 h.
     */
    private function timecodeLabel(int $seconds): string
    {
        return '[' . gmdate($seconds >= 3600 ? 'H:i:s' : 'i:s', $seconds) . ']';
    }
}
