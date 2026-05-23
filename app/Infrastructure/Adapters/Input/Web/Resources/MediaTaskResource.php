<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\TranscriptionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MediaTask $resource
 */
final class MediaTaskResource extends JsonResource
{
    /**
     * @param array<int, array{task_id: string, video_id: string, title: string, slug: string|null, similarity: float}> $similar
     */
    public function __construct($resource, private array $similar = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'task_id'     => $this->resource->id(),
            'status'      => $this->resource->status()->value,
            'youtube_url' => $this->resource->youtubeUrl()->value(),
            'video_id'    => $this->resource->youtubeUrl()->videoId()->value(),
            'title'       => $this->resource->title(),
            'created_at'  => $this->resource->createdAt()->format('c'),
            '_links'      => [
                'self' => "/api/transcribe/{$this->resource->id()}",
            ],
        ];

        if ($this->resource->status() === TranscriptionStatus::Completed) {
            $data['duration_sec'] = $this->resource->durationSec();
            $data['result'] = [
                'transcript' => $this->resource->resultText()?->value(),
                'summary'    => $this->resource->summary() !== null
                    ? (new SummaryResource($this->resource->summary()))->toArray($request)
                    : null,
                'word_count' => $this->resource->resultText()?->wordCount(),
            ];
            $data['completed_at'] = $this->resource->completedAt()?->format('c');
            $data['_links']['download_txt'] = "/api/transcribe/{$this->resource->id()}/download";

            if ($this->resource->slug() !== null && ! $this->resource->isDmcaRemoved()) {
                $data['_links']['public_page'] = '/v/' . $this->resource->slug();
            }

            if (count($this->similar) > 0) {
                $data['similar'] = array_map(fn (array $t) => [
                    'task_id'    => $t['task_id'],
                    'video_id'   => $t['video_id'],
                    'title'      => $t['title'],
                    'similarity' => $t['similarity'],
                    '_links'     => array_filter([
                        'public_page' => $t['slug'] !== null ? '/v/' . $t['slug'] : null,
                        'youtube'     => 'https://youtube.com/watch?v=' . $t['video_id'],
                    ]),
                ], $this->similar);
            }
        }

        if ($this->resource->status() === TranscriptionStatus::Processing) {
            $data['estimated_completion_sec'] = 90;
        }

        if ($this->resource->status() === TranscriptionStatus::Failed) {
            $data['error_message'] = $this->resource->errorMessage();
            $data['failed_at'] = $this->resource->failedAt()?->format('c');
        }

        return $data;
    }
}
