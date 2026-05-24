<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\ValueObjects\SummaryResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SummaryResult $resource
 */
final class SummaryResource extends JsonResource
{
    /**
     * @return array{
     *     introduction: string,
     *     key_points: array<int, array{timecode: string, title: string, details: string}>,
     *     conclusion: string|null,
     *     resources: array<int, array{type: string, name: string, url: string|null}>,
     *     clickbait_verdict: array{score: int, comment: string}|null,
     *     tutorial_steps: array<int, array{step: int, time: string, action: string}>,
     *     chapters: array<int, array{title: string, start_timecode: string, end_timecode: string}>,
     *     flashcards: array<int, array{question: string, answer: string, source_timecode: string, difficulty: string}>,
     *     highlights: array<int, array{timecode: string, title: string, why_notable: string, category: string}>,
     *     content_meta: array{complexity: string, reading_time_minutes: int, jargon_density: string, target_audience: string}|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'introduction' => $this->resource->introduction(),
            'key_points' => array_map(
                fn (\App\Domain\ValueObjects\SummaryKeyPoint $kp) => [
                    'timecode' => $kp->timecode,
                    'title'    => $kp->title,
                    'details'  => $kp->details,
                ],
                $this->resource->keyPoints(),
            ),
            'conclusion'       => $this->resource->conclusion(),
            'resources'        => array_map(
                fn (\App\Domain\ValueObjects\ResourceItem $r) => [
                    'type' => $r->type,
                    'name' => $r->name,
                    'url'  => $r->url,
                ],
                $this->resource->resources(),
            ),
            'clickbait_verdict' => $this->resource->clickbaitVerdict()?->toArray(),
            'tutorial_steps'    => array_map(
                fn (\App\Domain\ValueObjects\TutorialStep $s) => [
                    'step'   => $s->step,
                    'time'   => $s->time,
                    'action' => $s->action,
                ],
                $this->resource->tutorialSteps(),
            ),
            'chapters' => array_map(
                fn (\App\Domain\ValueObjects\SummaryChapter $ch) => [
                    'title'          => $ch->title,
                    'start_timecode' => $ch->startTimecode,
                    'end_timecode'   => $ch->endTimecode,
                ],
                $this->resource->chapters(),
            ),
            'flashcards' => array_map(
                fn (\App\Domain\ValueObjects\Flashcard $fc) => [
                    'question'        => $fc->question,
                    'answer'          => $fc->answer,
                    'source_timecode' => $fc->sourceTimecode,
                    'difficulty'      => $fc->difficulty,
                ],
                $this->resource->flashCards(),
            ),
            'highlights' => array_map(
                fn (\App\Domain\ValueObjects\HighlightMoment $hm) => [
                    'timecode'    => $hm->timecode,
                    'title'       => $hm->title,
                    'why_notable' => $hm->whyNotable,
                    'category'    => $hm->category,
                ],
                $this->resource->highlights(),
            ),
            'content_meta' => $this->resource->contentMeta()?->toArray(),
        ];
    }
}
