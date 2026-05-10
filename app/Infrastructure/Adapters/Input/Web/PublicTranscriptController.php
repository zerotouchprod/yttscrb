<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

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
            ? mb_substr($summary, 0, 155)
            : null;

        $metaDescription = $summaryExcerpt !== null
            ? $summaryExcerpt . (mb_strlen($summary) > 155 ? '…' : '')
            : 'Full transcript and AI-generated summary of the YouTube video. No signup required.';

        $canonicalUrl = url('/v/' . $slug);

        return view('transcript', [
            'task'            => $task,
            'metaDescription' => $metaDescription,
            'canonicalUrl'    => $canonicalUrl,
        ]);
    }
}
