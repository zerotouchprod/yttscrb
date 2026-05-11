<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Domain\Entities\MediaTask;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

final class PublicTranscriptController extends Controller
{
    public function __construct(
        private readonly MediaTaskRepositoryInterface $repository,
    ) {
    }

    public function show(string $slug): View|Response
    {
        $task = $this->repository->findBySlug($slug);

        if ($task === null) {
            abort(404);
        }

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
     * Split transcript into ~80-word chunks with estimated timecodes.
     * Mirrors the groupedTranscript computed property in resources/js/App.vue.
     *
     * @return array<int, array{text: string, timeSec: int|null}>
     */
    private function chunkTranscript(MediaTask $task): array
    {
        $text = $task->resultText()?->value();
        if ($text === null || $text === '') {
            return [];
        }

        $words = preg_split('/\s+/', $text);
        if ($words === false) {
            return [];
        }

        $totalWords = count($words);
        $durationSec = $task->durationSec() ?? 0;
        $wordsPerSec = $durationSec > 0
            ? $totalWords / $durationSec
            : 0;

        $chunkSize = 80;
        $chunks = [];

        for ($i = 0; $i < $totalWords; $i += $chunkSize) {
            $timeSec = $wordsPerSec > 0
                ? (int) round($i / $wordsPerSec)
                : null;

            $chunks[] = [
                'text'    => implode(' ', array_slice($words, $i, $chunkSize)),
                'timeSec' => $timeSec,
            ];
        }

        return $chunks;
    }
}
