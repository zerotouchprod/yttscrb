<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\ViewTrackerInterface;
use App\Domain\Entities\MediaTask;
use App\Infrastructure\Adapters\Output\Queue\IncrementViewCountJob;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class PublicTranscriptController extends Controller
{
    public function __construct(
        private readonly MediaTaskRepositoryInterface $repository,
        private readonly ViewTrackerInterface $viewTracker,
    ) {
    }

    public function show(string $slug, Request $request): View|Response
    {
        $task = $this->repository->findBySlug($slug);

        if ($task === null) {
            abort(404);
        }

        $this->recordView($task, $request);

        $summary = $task->summary();
        $summaryExcerpt = $summary !== null
            ? mb_substr($summary->introduction(), 0, 155)
            : null;

        $metaDescription = $summaryExcerpt !== null
            ? $summaryExcerpt . (mb_strlen($summary->introduction()) > 155 ? '…' : '')
            : 'Full transcript and AI-generated summary of the YouTube video. No signup required.';

        $canonicalUrl = url('/v/' . $slug);

        $renderedSummary = $summary;

        // Chunk transcript into paragraphs with estimated timecodes (matching SPA logic)
        $transcriptChunks = $this->chunkTranscript($task);

        return view('transcript', [
            'task'             => $task,
            'metaDescription'  => $metaDescription,
            'canonicalUrl'     => $canonicalUrl,
            'renderedSummary'  => $renderedSummary,
            'transcriptChunks' => $transcriptChunks,
        ]);
    }

    /**
     * Record a page view: deduplicate by IP hash (1-hour window),
     * update Redis weekly sorted set, and dispatch async DB increment.
     */
    private function recordView(MediaTask $task, Request $request): void
    {
        $viewKey = hash('sha256', ($request->ip() ?? '') . $task->id());

        if ($this->viewTracker->isRecentlyViewed($viewKey, $task->id())) {
            return;
        }

        $this->viewTracker->markViewed($viewKey, $task->id());
        $this->viewTracker->recordWeeklyView($task->id());

        IncrementViewCountJob::dispatch($task->id());
    }

    /**
     * Split transcript into paragraphs.
     *
     * If the transcript contains embedded "[MM:SS]" or "[HH:MM:SS]" timecodes
     * (produced by SrtParser or GroqWhisperAdapter), parse them directly.
     * Otherwise fall back to ~80-word chunks with timecodes estimated from video duration.
     *
     * @return array<int, array{text: string, timeSec: int|null}>
     */
    private function chunkTranscript(MediaTask $task): array
    {
        $text = $task->resultText()?->value();
        if ($text === null || $text === '') {
            return [];
        }

        // Detect embedded timecodes on the first non-empty line
        $firstLine = '';
        foreach (explode("\n", $text) as $l) {
            $firstLine = trim($l);
            if ($firstLine !== '') {
                break;
            }
        }

        if (preg_match('/^\[(?:\d+:)?\d{1,2}:\d{2}\]/', $firstLine)) {
            return $this->parseTimedTranscript($text);
        }

        // Fallback: word-based chunks with estimated timecodes
        $words = preg_split('/\s+/', $text);
        if ($words === false) {
            return [];
        }

        $totalWords  = count($words);
        $durationSec = $task->durationSec() ?? 0;
        $wordsPerSec = $durationSec > 0 ? $totalWords / $durationSec : 0;
        $chunkSize   = 80;
        $chunks      = [];

        for ($i = 0; $i < $totalWords; $i += $chunkSize) {
            $timeSec  = $wordsPerSec > 0 ? (int) round($i / $wordsPerSec) : null;
            $chunks[] = [
                'text'    => implode(' ', array_slice($words, $i, $chunkSize)),
                'timeSec' => $timeSec,
            ];
        }

        return $chunks;
    }

    /**
     * Parse a timecoded transcript into chunks.
     * Each line must start with "[MM:SS]" or "[HH:MM:SS]".
     *
     * @return array<int, array{text: string, timeSec: int}>
     */
    private function parseTimedTranscript(string $text): array
    {
        $chunks = [];

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if (! preg_match('/^\[(?:(\d+):)?(\d{1,2}):(\d{2})\]\s*(.+)$/', $line, $m)) {
                continue;
            }
            $timeSec  = (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
            $chunks[] = ['text' => $m[4], 'timeSec' => $timeSec];
        }

        return $chunks;
    }
}
