<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Resources;

use App\Domain\Entities\MediaTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for GET /api/history/latest.
 * Does NOT include video_id (PRD §7.5).
 *
 * @property-read MediaTask $resource
 */
final class LatestMediaTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'task_id'      => $this->resource->id(),
            'youtube_url'  => $this->resource->youtubeUrl()->value(),
            'title'        => $this->resource->title(),
            'status'       => $this->resource->status()->value,
            'duration_sec' => $this->resource->durationSec(),
            'result'       => [
                'transcript' => $this->resource->resultText()?->value(),
                'summary'    => $this->resource->summary() !== null
                    ? new SummaryResource($this->resource->summary())->toArray($request)
                    : null,
                'word_count' => $this->resource->resultText()?->wordCount(),
            ],
            'created_at'   => $this->resource->createdAt()->format('c'),
            'completed_at' => $this->resource->completedAt()?->format('c'),
            '_links'       => [
                'download_txt' => "/api/transcribe/{$this->resource->id()}/download",
            ],
        ];
    }
}
